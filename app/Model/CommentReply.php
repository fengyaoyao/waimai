<?php
namespace App\Model;
use Illuminate\Database\Eloquent\Model;
class CommentReply extends Model
{
	protected $table      = 'comment_reply';
    protected $primaryKey = 'reply_id';
}