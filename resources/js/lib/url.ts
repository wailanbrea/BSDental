export const appBasePath = () => {
    if (typeof window === 'undefined') {
        return ''
    }

    return window.location.pathname.match(/^(.*\/public)(?:\/index\.php)?(?:\/|$)/)?.[1] ?? ''
}

export const appUrl = (path: string) => {
    if (!path.startsWith('/')) {
        return path
    }

    const base = appBasePath()

    if (base === '') {
        return path
    }

    return `${base}/index.php${path}`
}

export const normalizeAppPath = (path: string) => {
    const base = appBasePath()
    const indexBase = `${base}/index.php`

    if (base !== '' && path.startsWith(indexBase)) {
        return path.slice(indexBase.length) || '/'
    }

    return base !== '' && path.startsWith(base)
        ? path.slice(base.length) || '/'
        : path
}
