<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialPostTarget;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublishedController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $published = SocialPostTarget::whereHas('post', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'published')
            ->with(['post', 'account.provider'])
            ->orderByDesc('published_at')
            ->paginate(20);

        return view('social-studio.published', compact('published'));
    }
}
