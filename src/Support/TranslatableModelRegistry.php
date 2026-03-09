<?php

namespace PictaStudio\Translatable\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use PictaStudio\Translatable\Contracts\Translatable as TranslatableContract;
use PictaStudio\Translatable\Translatable as TranslatableTrait;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class TranslatableModelRegistry
{
    /**
     * @return array<int, class-string<Model&TranslatableContract>>
     */
    public function classes(): array
    {
        $models = [];

        foreach ($this->autoloadClassCandidates() as $class) {
            if ($this->isTranslatableModelClass($class) && $this->usesTranslatableTrait($class)) {
                $models[$class] = $class;
            }
        }

        foreach (get_declared_classes() as $class) {
            if ($this->isTranslatableModelClass($class) && $this->usesTranslatableTrait($class)) {
                $models[$class] = $class;
            }
        }

        ksort($models);

        return array_values($models);
    }

    /**
     * @return array<int, array{model:string,morph_alias:string,attributes:array<int,string>}>
     */
    public function descriptors(): array
    {
        return array_map(function (string $modelClass): array {
            /** @var Model&TranslatableContract $model */
            $model = new $modelClass;
            $morphClass = $model->getMorphClass();

            return [
                'model' => $modelClass,
                'morph_alias' => $morphClass === $modelClass ? $this->resolveMorphAlias($modelClass) : $morphClass,
                'attributes' => array_values($model->translatedAttributes),
            ];
        }, $this->classes());
    }

    protected function resolveMorphAlias(string $modelClass): string
    {
        foreach (Relation::morphMap() as $alias => $mappedClass) {
            if ($mappedClass === $modelClass) {
                return (string) $alias;
            }
        }

        return $modelClass;
    }

    protected function isTranslatableModelClass(mixed $modelClass): bool
    {
        return is_string($modelClass)
            && class_exists($modelClass)
            && is_subclass_of($modelClass, Model::class)
            && is_subclass_of($modelClass, TranslatableContract::class);
    }

    /**
     * @return array<int, class-string>
     */
    protected function autoloadClassCandidates(): array
    {
        $composerPath = base_path('composer.json');

        if (!is_file($composerPath)) {
            return [];
        }

        /** @var array{autoload?: array{psr-4?: array<string, string|array<int, string>>}, autoload-dev?: array{psr-4?: array<string, string|array<int, string>>}}|null $composer */
        $composer = json_decode((string) file_get_contents($composerPath), true);
        $psr4 = array_merge(
            Arr::get($composer, 'autoload.psr-4', []),
            Arr::get($composer, 'autoload-dev.psr-4', [])
        );

        if (!is_array($psr4)) {
            return [];
        }

        $classes = [];

        foreach ($psr4 as $namespace => $paths) {
            if (!is_string($namespace)) {
                continue;
            }

            foreach ((array) $paths as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }

                $classes = array_merge($classes, $this->classesFromDirectory($namespace, base_path($path)));
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * @return array<int, class-string>
     */
    protected function classesFromDirectory(string $namespace, string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $class = $namespace . str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $relativePath
            );

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    protected function usesTranslatableTrait(string $modelClass): bool
    {
        return in_array(TranslatableTrait::class, class_uses_recursive($modelClass), true);
    }
}
