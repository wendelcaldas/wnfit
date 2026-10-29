<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HelpCenterController extends Controller
{
    private function content(Request $request): array
    {
        $content = json_decode(file_get_contents(resource_path('content/help-center.json')), true, flags: JSON_THROW_ON_ERROR);

        return [...$content, 'base' => $request->getHost() === config('support.host') ? '' : '/ajuda', 'applicationUrl' => rtrim(config('support.application_url'), '/')];
    }

    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:120']);
        $content = $this->content($request);
        $query = Str::limit(trim((string) $request->query('q', '')), 120, '');
        $terms = preg_split('/\s+/', Str::lower(Str::ascii($query)), flags: PREG_SPLIT_NO_EMPTY);
        $results = collect($content['articles'])->filter(function ($article) use ($terms) {
            $text = Str::lower(Str::ascii(json_encode($article, JSON_UNESCAPED_UNICODE)));

            return collect($terms)->every(fn ($term) => str_contains($text, $term));
        })->values()->all();

        return response()->view('support.index', [...$content, 'query' => $query, 'results' => $results]);
    }

    public function collection(Request $request, string $slug)
    {
        $content = $this->content($request);
        $collection = collect($content['collections'])->firstWhere('slug', $slug);
        abort_unless($collection, 404);

        return response()->view('support.collection', [...$content, 'collection' => $collection, 'collectionArticles' => collect($content['articles'])->where('collection', $slug)->values()->all()]);
    }

    public function article(Request $request, string $slug)
    {
        $content = $this->content($request);
        $article = collect($content['articles'])->firstWhere('slug', $slug);
        abort_unless($article, 404);
        $collection = collect($content['collections'])->firstWhere('slug', $article['collection']);

        return response()->view('support.article', [...$content, 'article' => $article, 'collection' => $collection, 'related' => collect($content['articles'])->where('collection', $collection['slug'])->where('slug', '!=', $slug)->take(3)->values()->all()]);
    }
}
