import { AuthForm } from '../../components/auth-form';
export default function ResetPassword({ token, email, status }: { token: string; email: string; status?: string }) {
  return <AuthForm mode="reset" token={token} email={email ?? ''} status={status} />;
}
