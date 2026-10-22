import { AuthForm } from '../../components/auth-form';
export default function Register({ status }: { status?: string }) {
  return <AuthForm mode="register" status={status} />;
}
