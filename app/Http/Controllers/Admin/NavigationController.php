<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Themes\NavigationSchema;
use App\Core\Themes\StoreNavigation;
use App\Core\Themes\ThemeResolver;
use App\Domains\Catalog\Models\Category;
use App\Domains\Content\Models\Page;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NavigationController extends Controller
{
    public function index(Request $request, StoreNavigation $menus, ThemeResolver $themes): Response
    {
        return Inertia::render('admin/navigation/index', ['menus' => $menus->forEditing($themes->forRequest($request)), 'pages' => Page::orderBy('title')->get(['id', 'title', 'status']), 'categories' => Category::orderBy('name')->get(['id', 'name', 'parent_id', 'is_active'])]);
    }

    public function update(Request $request, StoreNavigation $menus, AuditLogger $audit): RedirectResponse
    {
        $data = NavigationSchema::validate($request->all());
        $menus->save($data);
        $audit->record('navigation.updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Navigation saved for headers, sidebars, mobile menus and footers across themes.']);

        return back();
    }
}
