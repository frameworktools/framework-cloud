<?php

return [

    /*
    | Web hosts that AI apps may send people back to after they approve a
    | connection, over https. Claude Code, Codex, and other apps on the
    | person's own computer use a loopback address, which is always allowed
    | over http. See App\Http\Controllers\OAuth\RegisterMcpClientController.
    */

    'redirect_hosts' => [
        'chatgpt.com',
        'chat.openai.com',
        'claude.ai',
        'claude.com',
    ],

    // laravel/mcp's own registration endpoint is not used; nothing may
    // register through it.
    'redirect_domains' => [],

];
