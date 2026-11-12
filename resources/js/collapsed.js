import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Whether a section is collapsed, remembered on the account so it follows the
// person across browsers. Saving happens in the background; the page doesn't reload.
export const useCollapsed = (key) => {
    const page = usePage();
    const collapsed = ref(!!page.props.auth?.user?.collapsed?.[key]);
    const toggle = () => {
        collapsed.value = !collapsed.value;
        // Laravel's XSRF cookie, sent back as the header it checks
        const token = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
        fetch('/preferences/ui', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ key, collapsed: collapsed.value }),
        }).catch(() => {});
    };
    return { collapsed, toggle };
};
