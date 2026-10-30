import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// The R package talks to framework.pub unless told otherwise, so commands
// shown on any other server name it: cloud_login("…", url = "<server>").
export const DEFAULT_SERVER = 'https://framework.pub';

export function useServerArg() {
    const page = usePage();

    return computed(() => (page.props.appUrl === DEFAULT_SERVER ? '' : `, url = "${page.props.appUrl}"`));
}
