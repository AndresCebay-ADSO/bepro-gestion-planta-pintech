import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { RetryableError } from '@/components/retryable-error';

describe('RetryableError', () => {
    it('anuncia el mensaje y deja «Reintentar» fuera del role="alert"', () => {
        const markup = renderToStaticMarkup(
            createElement(RetryableError, {
                message: 'No se pudo cargar.',
                onRetry: () => undefined,
            }),
        );

        // El aviso y su descripción se cierran antes de que empiece el botón.
        expect(markup).toMatch(
            /role="alert"[^>]*><div[^>]*>No se pudo cargar\.<\/div><\/div><button[^>]*>Reintentar/,
        );
    });
});
