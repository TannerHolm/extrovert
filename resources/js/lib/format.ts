export function formatFollowers(count: number | null): string {
    if (count === null) {
return 'N/A';
}

    if (count >= 1_000_000) {
return `${(count / 1_000_000).toFixed(1)}M`;
}

    if (count >= 1_000) {
return `${(count / 1_000).toFixed(1)}K`;
}

    return count.toString();
}

export function formatCents(cents: number | null): string {
    if (cents === null) {
        return 'N/A';
    }

    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
    });
}

export function formatDate(dateStr: string | null): string {
    if (!dateStr) {
return 'N/A';
}

    return new Date(dateStr).toLocaleDateString();
}
