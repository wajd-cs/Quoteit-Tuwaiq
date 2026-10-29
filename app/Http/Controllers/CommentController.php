<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentRequest;
use App\Models\Comment;
use App\Models\Quote;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(CommentRequest $request, Quote $quote)
    {
        // a scheduled quote opens for likes, saves and comments once it is published
        abort_if($quote->isHidden(), 403);

        $comment = Auth::user()->comments()->create([
            'quote_id' => $quote->id,
            'content' => $request->validated('content'),
        ]);

        $this->rewardBadges($request);

        return redirect()->to(route('quotes.show', $quote).'#comment-'.$comment->id);
    }

    public function destroy(Comment $comment)
    {
        // comment writer or quote owner
        abort_unless(
            $comment->user_id === Auth::id() || $comment->quote->user_id === Auth::id(),
            403
        );

        $comment->delete();

        return redirect()->to(route('quotes.show', $comment->quote_id).'#comments');
    }
}
