<?php

namespace Statamic\Stache\Repositories;

use Statamic\Contracts\Taxonomies\Term;
use Statamic\Contracts\Taxonomies\TermRepository as RepositoryContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Stache\Stache;
use Statamic\Support\Str;
use Statamic\Taxonomies\TermCollection;

class TermRepository implements RepositoryContract
{
    protected $stache;
    protected $store;

    public function __construct(Stache $stache)
    {
        $this->stache = $stache;
        $this->store = $stache->store('terms');
    }

    public function all(): TermCollection
    {
        return $this->query()->get();
    }

    public function whereTaxonomy(string $handle): TermCollection
    {
        return $this->query()->where('taxonomy', $handle)->get();
    }

    public function whereInTaxonomy(array $handles): TermCollection
    {
        return $this->query()->whereIn('taxonomy', $handles)->get();
    }

    public function find($id): ?Term
    {
        return $this->query()->where('id', $id)->first();
    }

    public function findByUri(string $uri, string $site = null): ?Term
    {
        $collection = Collection::all()
            ->first(function ($collection) use ($uri, $site) {
                if (Str::startsWith($uri, $collection->uri($site))) {
                    return true;
                }

                return Str::startsWith($uri, '/'.$collection->handle());
            });

        if ($collection) {
            $uri = Str::after($uri, $collection->uri($site) ?? $collection->handle());
        }

        
        $uri = Str::after($uri, '/');

        $taxonomy = Str::beforeLast($uri, '/');
        $slug = Str::afterLast($uri, '/');
        
        if (! $slug) {
            return null;
        }

        if (! $taxonomy = $this->findTaxonomyHandleByUri($taxonomy)) {
            return null;
        }

        $term = $this->query()
            ->where('slug', $slug)
            ->where('taxonomy', $taxonomy)
            ->where('site', $site)
            ->first();

        if (! $term) {
            return null;
        }

        return $term->collection($collection);
    }

    /** @deprecated */
    public function findBySlug(string $slug, string $taxonomy): ?Term
    {
        return $this->query()
            ->where('slug', $slug)
            ->where('taxonomy', $taxonomy)
            ->first();
    }

    public function save($term)
    {
        $this->store
            ->store($term->taxonomyHandle())
            ->save($term);
    }

    public function delete($term)
    {
        $this->store
            ->store($term->taxonomyHandle())
            ->delete($term);
    }

    public function query()
    {
        $this->ensureAssociations();

        return new TermQueryBuilder($this->store);
    }

    public function make(string $slug = null): Term
    {
        return app(Term::class)->slug($slug);
    }

    public function entriesCount(Term $term): int
    {
        return $this->store->store($term->taxonomyHandle())
            ->index('associations')
            ->items()
            ->where('value', $term->slug())
            ->count();
    }

    protected function ensureAssociations()
    {
        Taxonomy::all()->each(function ($taxonomy) {
            $this->store->store($taxonomy->handle())->index('associations');
        });
    }

    public static function bindings(): array
    {
        return [
            Term::class => \Statamic\Taxonomies\Term::class,
        ];
    }

    private function findTaxonomyHandleByUri($uri)
    {
        return $this->stache->store('taxonomies')->index('uri')->items()->flip()->get(Str::ensureLeft($uri, '/'));
    }
}
