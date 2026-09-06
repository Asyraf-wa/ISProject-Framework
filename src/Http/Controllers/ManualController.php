<?php

namespace IsProject\Framework\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use IsProject\Framework\Support\Manual;

/**
 * The manual.
 *
 * Read-only by design: there is nothing here to submit, so nothing to guard
 * beyond being signed in.
 */
class ManualController extends Controller
{
    public function __construct(private Manual $manual) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('isproject::manual.index', [
            'chapters' => $this->manual->chapters(),
            'search' => $search,
            'results' => $search === '' ? null : $this->manual->search($search),
        ]);
    }

    public function show(string $chapter): View
    {
        // Looked up against the discovered chapters rather than joined onto a
        // path, so there is no filename here for a request to steer.
        $found = $this->manual->find($chapter);

        abort_if($found === null, 404, 'There is no such chapter in the manual.');

        return view('isproject::manual.show', [
            'chapter' => $found,
            'chapters' => $this->manual->chapters(),
        ] + $this->manual->neighbours($chapter));
    }
}
