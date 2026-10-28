export function el(tag, className = '', text = null) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== null) node.textContent = text;
    return node;
}

export const icon = (name) => el('i', `fa-solid ${name}`);

export function badge(text, tone, iconName = null) {
    const b = el('span', `badge badge--${tone}`);
    if (iconName) b.append(icon(iconName), document.createTextNode(' '));
    b.append(document.createTextNode(text));
    return b;
}

export const fmtDateTime = (iso) => (iso ? new Date(iso).toLocaleString(document.documentElement.lang || undefined) : '');