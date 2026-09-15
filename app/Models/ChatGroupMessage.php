<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatGroupMessage extends Model
{
    use HasFactory;

    protected $fillable = ['chat_group_id', 'sender_id', 'body'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class, 'chat_group_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public static function unreadFor(User $user): Builder
    {
        return static::query()
            ->select('chat_group_messages.*')
            ->join('chat_group_members as group_membership', function ($join) use ($user): void {
                $join->on('group_membership.chat_group_id', '=', 'chat_group_messages.chat_group_id')
                    ->where('group_membership.user_id', $user->id);
            })
            ->where('chat_group_messages.sender_id', '!=', $user->id)
            ->where(fn ($messages) => $messages
                ->whereNull('group_membership.last_read_at')
                ->orWhereColumn('chat_group_messages.created_at', '>', 'group_membership.last_read_at'));
    }
}
