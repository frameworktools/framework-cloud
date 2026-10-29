<?php

namespace App\Services\GitHub;

use RuntimeException;

/** The user has no usable GitHub connection; they need to connect again. */
class GitHubNotConnected extends RuntimeException {}
