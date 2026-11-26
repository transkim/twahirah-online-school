<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Navigation;
use Illuminate\View\View;

class SectionController extends Controller
{
    /**
     * Show a menu section that is planned but not built yet, with what it will do.
     */
    public function __invoke(Navigation $navigation, string $slug): View
    {
        $section = $navigation->section($slug);

        abort_if($section === null, 404);

        return view('admin.section', ['section' => $section]);
    }
}
