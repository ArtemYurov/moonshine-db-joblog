<?php

declare(strict_types=1);

namespace ArtemYurov\JobLog\Support;

use ArtemYurov\JobLog\Traits\Loggable;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers job classes that use the Loggable trait.
 */
final class JobClassDiscovery
{
    /**
     * Scan configured paths and return all job classes using the Loggable trait.
     *
     * @return Collection<int, class-string>
     */
    public static function discover(): Collection
    {
        $scanPaths = config('joblog.job_class_scan_paths', []);

        if (empty($scanPaths)) {
            $scanPaths = [app_path('Jobs')];
        }

        $classes = collect();

        foreach ($scanPaths as $jobsPath) {
            if (!is_dir($jobsPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($jobsPath)
            );

            $pathClasses = collect($iterator)
                ->filter(fn($file) => $file->isFile() && $file->getExtension() === 'php')
                ->map(function ($file) use ($jobsPath) {
                    $relativePath = str_replace($jobsPath . '/', '', $file->getPathname());
                    return 'App\\Jobs\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
                })
                ->filter(fn($className) => class_exists($className) && in_array(Loggable::class, class_uses_recursive($className)));

            $classes = $classes->merge($pathClasses);
        }

        return $classes;
    }
}
