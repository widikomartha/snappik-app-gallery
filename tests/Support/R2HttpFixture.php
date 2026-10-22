<?php

namespace Tests\Support;

use Aws\S3\S3Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

/** Local HTTP transport fixture for the real AWS SDK; never calls Cloudflare. */
final class R2HttpFixture
{
    public array $operations = [];

    public array $partSizes = [];

    public ?int $failPart = null;

    public bool $wrongHead = false;

    public bool $failCleanup = false;

    public bool $failComplete = false;

    public array $readRequests = [];

    public ?string $badRange = null;

    public int $interruptedReads = 0;

    public ?string $orphanKey = null;

    public function __construct(public string $path) {}

    public function client(): S3Client
    {
        return new S3Client(['version' => '2006-03-01', 'region' => 'auto',
            'endpoint' => 'https://r2.synthetic.test', 'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'synthetic-key', 'secret' => 'synthetic-secret'], 'retries' => 0,
            'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
            'http_handler' => $this]);
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        parse_str($request->getUri()->getQuery(), $query);
        $method = $request->getMethod();
        if ($method === 'POST' && array_key_exists('uploads', $query)) {
            $this->operations[] = 'create';
            file_put_contents($this->path, '');

            return Create::promiseFor(new Response(200, [], '<InitiateMultipartUploadResult><UploadId>synthetic-upload</UploadId></InitiateMultipartUploadResult>'));
        }
        if ($method === 'PUT' && isset($query['partNumber'])) {
            $number = (int) $query['partNumber'];
            $this->operations[] = 'part';
            if ($number === $this->failPart) {
                return $this->error($request);
            }
            $target = fopen($this->path, 'ab');
            $body = $request->getBody();
            $size = 0;
            $hash = hash_init('md5');
            while (! $body->eof()) {
                $chunk = $body->read(1024 * 1024);
                $size += strlen($chunk);
                hash_update($hash, $chunk);
                fwrite($target, $chunk);
            }
            fclose($target);
            $this->partSizes[] = $size;
            if ($request->getHeaderLine('Content-MD5') !== base64_encode(hash_final($hash, true))) {
                throw new \LogicException('Multipart Content-MD5 missing or incorrect');
            }

            return Create::promiseFor(new Response(200, ['ETag' => '"synthetic-part-'.$number.'"']));
        }
        if ($method === 'POST' && isset($query['uploadId'])) {
            $this->operations[] = 'complete';
            if ($this->failComplete) {
                return $this->error($request);
            }

            return Create::promiseFor(new Response(200, [], '<CompleteMultipartUploadResult><ETag>synthetic-complete</ETag></CompleteMultipartUploadResult>'));
        }
        if ($method === 'HEAD') {
            $this->operations[] = 'head';
            clearstatcache(true, $this->path);

            return Create::promiseFor(new Response(200, ['Content-Length' => (string) (filesize($this->path) + ($this->wrongHead ? 1 : 0)), 'Content-Type' => 'application/zip', 'ETag' => '"synthetic-complete"']));
        }
        if ($method === 'GET' && array_key_exists('uploads', $query)) {
            $this->operations[] = 'list';
            $this->readRequests[] = ['prefix' => $query['prefix'] ?? null, 'marker' => $query['key-marker'] ?? null];
            $key = htmlspecialchars($this->orphanKey ?? '', ENT_XML1);
            $last = isset($query['key-marker']);
            $xml = '<ListMultipartUploadsResult><IsTruncated>'.($last ? 'false' : 'true').'</IsTruncated>'
                .($last ? '' : '<NextKeyMarker>'.$key.'</NextKeyMarker><NextUploadIdMarker>one</NextUploadIdMarker>')
                .'<Upload><Key>'.$key.'</Key><UploadId>'.($last ? 'two' : 'one').'</UploadId></Upload>'
                .'<Upload><Key>backups/do-not-touch</Key><UploadId>backup</UploadId></Upload></ListMultipartUploadsResult>';

            return Create::promiseFor(new Response(200, [], $xml));
        }
        if ($method === 'GET') {
            $this->operations[] = 'get';
            $this->readRequests[] = ['range' => $request->getHeaderLine('Range'), 'if_match' => $request->getHeaderLine('If-Match'), 'stream' => $options['stream'] ?? null, 'timeout' => $options['timeout'] ?? null, 'read_timeout' => $options['read_timeout'] ?? null];
            if (preg_match('/\Abytes=(\d+)-(\d+)\z/', $request->getHeaderLine('Range'), $range)) {
                $start = (int) $range[1];
                $length = (int) $range[2] - $start + 1;
                $body = new LimitStream(Utils::streamFor(fopen($this->path, 'rb')), $length, $start);
                if ($this->interruptedReads > 0) {
                    $this->interruptedReads--;
                    $original = $body;
                    $first = true;
                    $body = FnStream::decorate($original, ['read' => function (int $length) use ($original, &$first): string {
                        if (! $first) {
                            throw new \RuntimeException('synthetic-private-read-error');
                        }
                        $first = false;

                        return $original->read(min($length, 1));
                    }]);
                }
                $headers = ['Content-Type' => 'application/zip', 'Content-Length' => (string) $length,
                    'Content-Range' => 'bytes '.$start.'-'.$range[2].'/'.filesize($this->path), 'ETag' => '"synthetic-complete"'];
                if ($this->badRange === 'etag') {
                    $headers['ETag'] = '"synthetic-changed"';
                }
                if ($this->badRange === 'range') {
                    $headers['Content-Range'] = 'bytes 0-0/1';
                }
                if ($this->badRange === 'truncated') {
                    $body = 'x';
                }
                if ($this->badRange === 'surplus') {
                    $body = str_repeat('x', $length + 1);
                }

                return Create::promiseFor(new Response($this->badRange === 'status' ? 200 : 206, $headers, $body));
            }

            return Create::promiseFor(new Response(200, ['Content-Type' => 'application/zip'], fopen($this->path, 'rb')));
        }
        if ($method === 'DELETE') {
            $this->operations[] = isset($query['uploadId']) ? 'abort' : 'delete';
            if ($this->failCleanup) {
                return $this->error($request);
            }
            if (is_file($this->path)) {
                unlink($this->path);
            }

            return Create::promiseFor(new Response(204));
        }
        throw new \LogicException('Unexpected fixture operation');
    }

    private function error(RequestInterface $request): PromiseInterface
    {
        return Create::rejectionFor(new RequestException('Synthetic transport failure', $request,
            new Response(503, [], '<Error><Code>ServiceUnavailable</Code><Message>Synthetic failure</Message></Error>')));
    }
}
