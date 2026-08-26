import '../css/app.css';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { appUrl } from '@/lib/url';

const appName = import.meta.env.VITE_APP_NAME || 'BSDental';

type VisitUrl = Parameters<typeof router.visit>[0];

function resolveVisitUrl(url: VisitUrl): VisitUrl {
    if (typeof url === 'string' || url instanceof URL) {
        return typeof url === 'string' ? appUrl(url) : url;
    }

    return { ...url, url: appUrl(url.url) };
}

const visit = router.visit.bind(router);
router.visit = ((url, options) => visit(resolveVisitUrl(url), options)) as typeof router.visit;

document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    const anchor = target.closest<HTMLAnchorElement>('a[href]');
    if (anchor === null) return;

    const href = anchor.getAttribute('href');
    if (href !== null) anchor.setAttribute('href', appUrl(href));
}, true);

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#0F766E',
        showSpinner: false,
    },
});
