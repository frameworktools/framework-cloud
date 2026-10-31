import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// The R package talks to framework.pub unless told otherwise, so commands
// shown on any other server name it: cloud_login("…", url = "<server>").
export const DEFAULT_SERVER = 'https://framework.pub';

export function useServerArg() {
    const page = usePage();

    return computed(() => (page.props.appUrl === DEFAULT_SERVER ? '' : `, url = "${page.props.appUrl}"`));
}

// fw takes the server as a flag: fw new --from <slug> --url <server>
export function useServerFlag() {
    const page = usePage();

    return computed(() => (page.props.appUrl === DEFAULT_SERVER ? '' : ` --url ${page.props.appUrl}`));
}

// The command that sets up a project locally from a project token. fw never
// takes a token as an argument: it reads it from a hidden prompt. Python's
// new() uses the server cloud_login() connected to.

// Python's cloud_login() takes the server as url="<server>"
export function usePythonServerArg() {
    const page = usePage();

    return computed(() => (page.props.appUrl === DEFAULT_SERVER ? '' : `, url="${page.props.appUrl}"`));
}

export function useSetupCommand() {
    const arg = useServerArg();
    const flag = useServerFlag();

    return (language, token) => {
        if (language === 'stata') return `fw new --pull-token${flag.value}`;
        if (language === 'python') return `framework.new(key="${token}")`;
        return `framework::setup("${token}"${arg.value})`;
    };
}
