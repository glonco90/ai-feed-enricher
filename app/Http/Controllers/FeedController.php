<?php

namespace App\Http\Controllers;

use App\Jobs\EnrichProduct;
use App\Models\Feed;
use App\Models\Product;
use App\Services\FeedParser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FeedController extends Controller
{
    public const SAMPLE_FEED = 'resources/samples/supplier-feed.xml';

    public function index(): View
    {
        return view('feeds.index', [
            'feeds' => Feed::query()->withCount('products')->latest()->get(),
        ]);
    }

    public function store(Request $request, FeedParser $parser): RedirectResponse
    {
        if ($request->boolean('sample')) {
            $path = base_path(self::SAMPLE_FEED);
            $filename = basename($path);
            $extension = 'xml';
        } else {
            $request->validate([
                'feed' => ['required', 'file', 'extensions:xml,csv,txt', 'max:5120'],
            ], [
                'feed.required' => 'Vyber súbor s feedom.',
                'feed.extensions' => 'Feed musí byť XML alebo CSV.',
            ]);

            $file = $request->file('feed');
            $path = $file->getRealPath();
            $filename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
        }

        try {
            $rawProducts = $parser->parse($path, $extension);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['feed' => $e->getMessage()]);
        }

        if ($rawProducts === []) {
            return back()->withErrors(['feed' => 'Vo feede nie sú žiadne produkty.']);
        }

        $feed = Feed::import(pathinfo($filename, PATHINFO_FILENAME), $filename, $rawProducts);

        return to_route('feeds.show', $feed)->with('status', 'Načítaných '.count($rawProducts).' produktov.');
    }

    public function show(Request $request, Feed $feed): View
    {
        $status = $request->query('status');

        return view('feeds.show', [
            'feed' => $feed,
            'stats' => $feed->stats(),
            'running' => $feed->isProcessing(),
            'status' => $status,
            'products' => $feed->products()
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderBy('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }

    public function process(Feed $feed): RedirectResponse
    {
        $products = $feed->products()->whereIn('status', [Product::PENDING, Product::FAILED])->get();

        foreach ($products as $product) {
            EnrichProduct::dispatch($product);
        }

        return back()->with('status', "Do fronty pridaných {$products->count()} produktov. Stránka sa obnovuje sama.");
    }

    public function approveAll(Feed $feed): RedirectResponse
    {
        $count = $feed->products()->where('status', Product::REVIEW)->update(['status' => Product::APPROVED]);

        return back()->with('status', "Schválených {$count} produktov.");
    }

    public function destroy(Feed $feed): RedirectResponse
    {
        $feed->delete();

        return to_route('feeds.index')->with('status', 'Feed bol zmazaný.');
    }
}
