/**
 * Outreach bodies exist in two shapes: plain text (AI drafts, templates,
 * legacy messages) and editor HTML. These helpers convert between them for
 * the WYSIWYG composer; the server re-sanitizes everything on send.
 */

export function isHtml(body: string): boolean {
    return /<[a-z][^>]*>/i.test(body);
}

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Lift a plain-text draft into paragraphs the editor understands. HTML
 * passes through untouched.
 */
export function toEditorHtml(body: string): string {
    if (body.trim() === '') {
        return '';
    }

    if (isHtml(body)) {
        return body;
    }

    return body
        .split(/\n{2,}/)
        .map((paragraph) => `<p>${escapeHtml(paragraph.trim()).replace(/\n/g, '<br>')}</p>`)
        .join('');
}

/**
 * Whether an editor document has any real content (an empty Tiptap doc is
 * still "<p></p>").
 */
export function hasContent(html: string): boolean {
    return html.replace(/<[^>]*>/g, '').trim() !== '';
}
