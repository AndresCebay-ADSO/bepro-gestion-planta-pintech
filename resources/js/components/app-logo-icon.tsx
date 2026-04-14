/**
 * AppLogoIcon Component
 * Displays the official Pintech brand logo (2026 version) as an image.
 * For full SVG version, use: /public/images/logo-pintech.svg
 * For brand guidelines, see: /docs/LOGOS.md
 */
import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(
    props: Omit<ImgHTMLAttributes<HTMLImageElement>, 'src'>,
) {
    return <img {...props} src="/images/logo-pintech.png" alt="Pintech logo" />;
}
