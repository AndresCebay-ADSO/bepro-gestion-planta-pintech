import { AuthFlashError } from '@/components/auth-flash-error';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate title={title} description={description}>
            <AuthFlashError />
            {children}
        </AuthLayoutTemplate>
    );
}
