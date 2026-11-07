<?php

namespace App\Support;

class GitignoreTemplate
{
    /** Structure rules supplement the language-specific defaults supplied by clients. */
    public static function for(string $type): string
    {
        $common = "# Private data and credentials\n**/private/**\n**/*_private/**\n.env\n.env.*\n!.env.example\n.Renviron\n**/credentials/**\n**/secrets/**\n\n# Local records and caches\nframework.db\nframework.db-wal\nframework.db-shm\n.DS_Store\n";

        return $common.match ($type) {
            'project_sensitive' => "\n# Sensitive project folders\n/inputs/private/\n/reference/private/\n/outputs/private/\n**/sensitive/**\n",
            'course' => "\n# Private course material\n/grades/\n/submissions/\n/student-data/\n",
            'presentation' => "\n# Presentation build cache\n.quarto/\n*_cache/\n",
            'project' => "\n# Analysis scratch work\n/scratch/\n/cache/\n",
            'shiny', 'plumber' => "\n# Deployment records\nrsconnect/\n.Rproj.user/\n",
            'shiny_python', 'fastapi' => "\n# Python environments and caches\n.venv/\n__pycache__/\n.pytest_cache/\nrsconnect-python/\n",
            default => '',
        };
    }
}
