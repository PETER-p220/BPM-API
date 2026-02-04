<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class CommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['register', 'login']);
    }

 // Display the last two comments posted by the logged-in user
    public function MyComments()
    {
        try {
            // Fetch comments created by the logged-in user, ordered by the latest comment
            $comments = Comment::with('user')
                ->where('user_id', Auth::id()) // Fetch only the comments of the logged-in user
                ->orderBy('created_at', 'desc') // Order by creation date
                ->take(2) // Get only the last two comments
                ->get();

            // If no comments are found, return an error message
            if ($comments->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No comments found for the logged-in user.',
                ], 404);
            }

            // Return the retrieved comments
            return response()->json([
                'status' => 'success',
                'message' => 'Last two comments retrieved successfully',
                'data' => $comments,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching comments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // Display the last two inserted comments
public function index()
{
    $comments = Comment::with('user')->orderBy('created_at', 'desc')->take(2)->get(); // Get only the last two comments

    if ($comments->isEmpty()) {
        return response()->json([
            'status' => 'error',
            'message' => 'No comments found',
        ], 404);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Last two comments retrieved successfully',
        'data' => $comments,
    ], 200);
}


    // Show a single comment by ID
    public function show($id)
    {
        $comment = Comment::with('user')->find($id);

        if (!$comment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Comment retrieved successfully',
            'data' => $comment,
        ], 200);
    }

    // Store a new comment
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'comment' => 'required|string|max:1000',
            ]);

            $comment = Comment::create([
                'comment' => $validatedData['comment'],
                'user_id' => Auth::id(),
            ]);

            // Send email notifications to all users except the one who posted
            $this->sendCommentNotification($comment);

            return response()->json([
                'status' => 'success',
                'message' => 'Comment posted successfully',
                'data' => $comment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while posting the comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update a comment
    public function update(Request $request, $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found',
            ], 404);
        }

        // Validate and update comment
        $validatedData = $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        $comment->update([
            'comment' => $validatedData['comment'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Comment updated successfully',
            'data' => $comment,
        ], 200);
    }

    // Delete a comment
    public function destroy($id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Comment not found',
            ], 404);
        }

        $comment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Comment deleted successfully',
        ], 200);
    }

    // Send email notifications about the new comment
    private function sendCommentNotification(Comment $comment)
    {
        $userEmails = User::where('user_id', '!=', Auth::id())->pluck('email')->toArray();

        $subject = 'New Comment Notification';
        $emailBody = "A new comment has been posted:\n\n"
            . "Comment: {$comment->comment}\n"
            . "Posted by: " . $comment->user->name . "\n\n";

        foreach ($userEmails as $email) {
            try {
                Mail::raw($emailBody, function ($message) use ($email, $subject) {
                    $message->to($email);
                    $message->subject($subject);
                });
            } catch (\Exception $e) {
                \Log::error("Failed to send email to {$email}: " . $e->getMessage());
            }
        }
    }
}
