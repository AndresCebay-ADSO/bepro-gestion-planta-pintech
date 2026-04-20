/**
 * AppLogoIcon Component
 * Displays the floral icon part of the Pintech brand logo.
 * Optimized for small sizes (sidebar collapsed, icons, etc).
 * For branding guidelines, see: /docs/LOGOS.md
 */
import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(
    props: Omit<ImgHTMLAttributes<HTMLImageElement>, 'src'>,
) {
    return (
        <img
            {...props}
            src="/images/logo-icon.svg?v=1.1"
            alt="Pintech Icon"
            width={32}
            height={32}
        />
    );


}

