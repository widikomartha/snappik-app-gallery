import { AuthForm } from '../../components/auth-form';
export default function Login({ status }: { status?: string }) {
  return <AuthForm mode="login" status={status} />;
}
