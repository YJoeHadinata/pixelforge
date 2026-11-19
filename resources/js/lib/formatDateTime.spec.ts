import { describe, expect, it } from 'vitest';
import { formatLocalTimestamp, formatUtcTimestamp } from './formatDateTime';

describe('formatDateTime', () => {
    it('formats ISO-8601 UTC timestamps into readable local and UTC strings', () => {
        const iso = '2026-11-19T06:34:50+00:00';

        expect(formatLocalTimestamp(iso, 'Asia/Jakarta')).toBe('Nov 19, 2026, 13:34');
        expect(formatUtcTimestamp(iso)).toBe('Nov 19, 2026, 06:34:50 UTC');
    });

    it('returns an em dash for null or empty values and preserves invalid strings', () => {
        expect(formatLocalTimestamp(null)).toBe('—');
        expect(formatUtcTimestamp(undefined)).toBe('—');
        expect(formatLocalTimestamp('not-a-date')).toBe('not-a-date');
    });
});
