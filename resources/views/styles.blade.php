{{-- Explain's own buttons. Simplify's tokens first, Pelican's (Filament) colours without Simplify; never fixed colours. --}}
<style>
    .sx-problem-btn {
        display: inline-grid;
        place-items: center;
        width: 1.5rem;
        height: 1.5rem;
        margin-inline-start: 0.375rem;
        vertical-align: middle;
        border: 1px solid var(--ps-line, color-mix(in srgb, var(--gray-400, currentColor) 35%, transparent));
        border-radius: 0.375rem;
        background: transparent;
        color: inherit;
        cursor: pointer;
    }
    .sx-problem-btn:hover,
    .sx-problem-btn:focus-visible {
        border-color: var(--ps-accent, var(--primary-600, currentColor));
        outline: none;
    }
    .sx-problem-btn svg {
        width: 1rem;
        height: 1rem;
    }
    .sx-error-link {
        display: flex;
        gap: 0.625rem;
        align-items: flex-start;
        max-width: 28rem;
        margin: 1rem auto;
        padding: 0.75rem 1rem;
        border: 1px dashed var(--primary-500, currentColor);
        border-radius: 0.75rem;
        color: inherit;
        text-decoration: none;
    }
    .sx-error-link:hover,
    .sx-error-link:focus-visible {
        border-style: solid;
    }
    .sx-error-link svg {
        flex: none;
        width: 1.25rem;
        height: 1.25rem;
        margin-top: 0.125rem;
        color: var(--primary-500, currentColor);
    }
    .sx-error-link span span {
        opacity: 0.75;
    }
</style>
