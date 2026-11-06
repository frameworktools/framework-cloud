import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// The R package talks to framework.pub unless told otherwise, so commands
// shown on any other server name it: cloud_login("…", url = "<server>").
export const DEFAULT_SERVER = 'https://framework.pub';

// The address R, Python, and fw reach this server at: FW_CLIENT_URL, or APP_URL
export const clientUrl = (page) => page.props.clientUrl ?? page.props.appUrl;

export function useServerArg() {
    const page = usePage();

    return computed(() => (clientUrl(page) === DEFAULT_SERVER ? '' : `, url = "${clientUrl(page)}"`));
}

// fw takes the server as a flag: fw new --from <slug> --url <server>
export function useServerFlag() {
    const page = usePage();

    return computed(() => (clientUrl(page) === DEFAULT_SERVER ? '' : ` --url ${clientUrl(page)}`));
}

// The command that sets up a project locally from a project token. fw never
// takes a token as an argument: it reads it from a hidden prompt. R's setup()
// and Python's new() take the server as url, so a self-hosted server works
// without a login first.

// Python's cloud_login() takes the server as url="<server>"
export function usePythonServerArg() {
    const page = usePage();

    return computed(() => (clientUrl(page) === DEFAULT_SERVER ? '' : `, url="${clientUrl(page)}"`));
}

export function useSetupCommand() {
    const arg = useServerArg();
    const pyArg = usePythonServerArg();
    const flag = useServerFlag();

    return (language, token) => {
        if (language === 'stata') return `fw new --pull-token${flag.value}`;
        if (language === 'python') return `framework.new(key="${token}"${pyArg.value})`;
        return `framework::setup("${token}"${arg.value})`;
    };
}
