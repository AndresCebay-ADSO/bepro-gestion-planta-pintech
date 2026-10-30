import { describe, expect, it } from 'vitest';
import { breadcrumbHref } from './navigation';

describe('breadcrumbHref', () => {
    it('keeps the filters when returnTo is the same screen', () => {
        expect(breadcrumbHref('/products?search=x&page=2', '/products')).toBe(
            '/products?search=x&page=2',
        );
    });

    it('ignores returnTo from another module', () => {
        expect(
            breadcrumbHref('/finished-inventory?search=x', '/products'),
        ).toBe('/products');
    });

    it('falls back to the screen without returnTo', () => {
        expect(breadcrumbHref(null, '/products')).toBe('/products');
        expect(breadcrumbHref(undefined, '/products')).toBe('/products');
        expect(breadcrumbHref('', '/products')).toBe('/products');
    });

    it('compares only the path when returnTo carries a nested return_to', () => {
        const fromProduct = `/products/5?return_to=${encodeURIComponent('/products?search=x')}`;

        expect(breadcrumbHref(fromProduct, '/products/5')).toBe(fromProduct);
        expect(breadcrumbHref(fromProduct, '/products')).toBe('/products');
        expect(
            breadcrumbHref(
                `/formulas?return_to=${encodeURIComponent('/products/5')}`,
                '/products/5',
            ),
        ).toBe('/products/5');
    });
});
