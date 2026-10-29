<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdvanceReadCursorRequest;
use App\Http\Requests\Api\StoreDirectConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function index(Request $request, ConversationService $conversations): AnonymousResourceCollection
    {
        return ConversationResource::collection(
            $conversations->summariesFor($request->user()),
        );
    }

    public function storeDirect(
        StoreDirectConversationRequest $request,
        ConversationService $conversations,
    ): ConversationResource {
        $other = User::query()
            ->where('username', $request->validated('username'))
            ->where('status', AccountStatus::Active)
            ->firstOrFail();

        $conversation = $conversations->openDirect($request->user(), $other);

        return new ConversationResource(
            $conversations->prepareForViewer($request->user(), $conversation),
        );
    }

    public function show(
        Request $request,
        Conversation $conversation,
        ConversationService $conversations,
    ): ConversationResource {
        $this->authorize('view', $conversation);

        return new ConversationResource(
            $conversations->prepareForViewer($request->user(), $conversation),
        );
    }

    public function read(
        AdvanceReadCursorRequest $request,
        Conversation $conversation,
        ConversationService $conversations,
    ): JsonResponse {
        $this->authorize('read', $conversation);

        $lastReadSeq = $conversations->markRead(
            $request->user(),
            $conversation,
            (int) $request->validated('seq'),
        );

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'last_read_seq' => $lastReadSeq,
            ],
        ]);
    }
}
