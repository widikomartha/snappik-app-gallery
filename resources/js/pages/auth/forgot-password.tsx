import { AuthForm } from '../../components/auth-form';
export default function ForgotPassword({ status }: { status?: string }) {
  return <AuthForm mode="forgot" status={status} />;
}
