<?php namespace App\Http\Controllers\Board;

use App\Ban;
use App\Board;
use App\Post;
use App\Http\Controllers\MainController;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Registrar;
use Input;
use Request;
use Validator;
use View;

class PostController extends MainController {
	
	/*
	|--------------------------------------------------------------------------
	| Post Controller
	|--------------------------------------------------------------------------
	|
	| Any request to be acted upon a single, extant post is carried out through
	| this form.
	| 
	| Note that creating a NEW post against a board is handled
	| in the BoardController instead.
	| 
	*/
	
	const VIEW_EDIT = "content.forms.mod";
	const VIEW_MOD  = "content.forms.mod";
	
	/**
	 * 
	 */
	public function getMod(Request $request, Board $board, $post)
	{
		// Validate the request parameters.
		if(!(($post = $this->validatePost($board, $post)) instanceof Post))
		{
			// If the response isn't a Post, it's a redirect or error.
			// Return the message.
			return $post;
		}
		
		// Take trailing arguments,
		// compare them against a list of real actions,
		// intersect the liss to find the true commands.
		$actions    = ["delete", "ban", "all", "global"];
		$argList    = func_get_args();
		$modActions = array_intersect($actions, array_splice($argList, 2));
		sort($modActions);
		
		$ban        = in_array("ban",    $modActions);
		$delete     = in_array("delete", $modActions);
		$all        = in_array("all",    $modActions);
		$global     = in_array("global", $modActions);
		
		if (!$ban && !$delete)
		{
			return abort(404);
		}
		
		if ($ban)
		{
			return View::make(static::VIEW_MOD, [
				"actions" => $modActions,
				"form"    => "ban",
				"board"   => $board,
				"post"    => $post,
			]);
		}
		else if ($delete)
		{
			if ($global)
			{
				if (!$this->user->canDeleteGlobally())
				{
					return abort(403);
				}
				
				
				$posts = Post::where('author_ip', $post->author_ip);
				
				$this->log('log.post.delete.global', $post, [
					"board_id"  => $post->board_id,
					"board_uri" => $post->board_uri,
					"ip"        => $post->author_ip,
					"posts"     => $posts->count(),
				]);
				
				$posts->delete();
				
				return redirect($board->board_uri);
			}
			else
			{
				if (!$board->canDelete($this->user))
				{
					return abort(403);
				}
				
				if ($all)
				{
					$posts = Post::where('author_ip', $post->author_ip)
						->where('board_uri', $board->board_uri);
					
					$this->log('log.post.delete.local', $post, [
						"board_id"  => $post->board_id,
						"board_uri" => $post->board_uri,
						"ip"        => $post->author_ip,
						"posts"     => $posts->count(),
					]);
					
					$posts->delete();
					
					return redirect($board->board_uri);
				}
				else
				{
					if ($post->author_ip != Request::ip())
					{
						if ($post->reply_to)
						{
							$this->log('log.post.delete.reply', $post, [
								"board_id"  => $post->board_id,
								"board_uri" => $post->board_uri,
								"op_id"     => $post->op->board_id,
							]);
						}
						else
						{
							$this->log('log.post.delete.op', $post, [
								"board_id"  => $post->board_id,
								"board_uri" => $post->board_uri,
								"replies"   => $post->replies()->count(),
							]);
						}
					}
					
					$post->delete();
					
					if ($post->reply_to)
					{
						return redirect("{$post->board_uri}/thread/{$post->op->board_id}");
					}
					else
					{
						return redirect($board->board_uri);
					}
				}
			}
		}
		
		return abort(403);
	}
	
	/**
	 *
	 */
	public function putMod(Request $request, Board $board, $post)
	{
		// Validate the request parameters.
		if(!(($post = $this->validatePost($board, $post)) instanceof Post))
		{
			// If the response isn't a Post, it's a redirect or error.
			// Return the message.
			return $post;
		}
		
		// Take trailing arguments,
		// compare them against a list of real actions,
		// intersect the liss to find the true commands.
		$actions    = ["delete", "ban", "all", "global"];
		$argList    = func_get_args();
		$modActions = array_intersect($actions, array_splice($argList, 2));
		sort($modActions);
		
		$ban        = in_array("ban",    $modActions);
		$delete     = in_array("delete", $modActions);
		$all        = in_array("all",    $modActions);
		$global     = in_array("global", $modActions);
		
		if (!$ban)
		{
			return abort(404);
		}
		
		
		Validator::make(Input::all(), [
			'ban_ip'          => 'required|ip',
			'justification'   => 'max:255',
			'expires_days'    => 'required|min:0|max:30',
			'expires_hours'   => 'required|min:0|max:23',
			'expires_minutes' => 'required|min:0|max:59',
		]);
		
		$banLengthStr   = [];
		$expiresDays    = Input::get('expires_days');
		$expiresHours   = Input::get('expires_hours');
		$expiresMinutes = Input::get('expires_minutes');
		
		if ($expiresDays > 0)
		{
			$banLengthStr[] = "{$expiresDays}d";
		}
		if ($expiresHours > 0)
		{
			$banLengthStr[] = "{$expiresHours}h";
		}
		if ($expiresMinutes > 0)
		{
			$banLengthStr[] = "{$expiresMinutes}m";
		}
		if ($expiresDays == 0 && $expiresHours == 0 && $expiresMinutes == 0)
		{
			$banLengthStr[] = "&Oslash;";
		}
		
		$banLengthStr = implode($banLengthStr, " ");
		
		$ban = new Ban();
		$ban->ban_ip        = Input::get('ban_ip');
		$ban->seen          = false;
		$ban->created_at    = $ban->freshTimestamp();
		$ban->updated_at    = clone $ban->created_at;
		$ban->expires_at    = clone $ban->created_at;
		$ban->expires_at->addDays($expiresDays);
		$ban->expires_at->addHours($expiresHours);
		$ban->expires_at->addMinutes($expiresMinutes);
		$ban->mod_id        = $this->user->user_id;
		$ban->post_id       = $post->post_id;
		$ban->ban_reason_id = null;
		$ban->justification = Input::get('justification');
		
		if ($global)
		{
			if (($ban && !$this->user->canBanGlobally()) || ($delete && !$this->user->canDeleteGlobally()))
			{
				return abort(403);
			}
			
			if ($ban)
			{
				$ban->board_uri = null;
				$ban->save();
			}
			
			$this->log('log.post.ban.global', $post, [
				"board_id"      => $post->board_id,
				"board_uri"     => $post->board_uri,
				"ip"            => $post->author_ip,
				"justification" => $ban->justification,
				"time"          => $banLengthStr,
			]);
			
			if ($delete)
			{
				$posts = Post::where('author_ip', $post->author_ip);
				
				$this->log('log.post.ban.delete', $post, [
					"board_id"  => $post->board_id,
					"board_uri" => $post->board_uri,
					"posts"     => $posts->count(),
				]);
				
				$posts->delete();
				
				return redirect($board->board_uri);
			}
		}
		else
		{
			if (($ban && !$board->canBan($this->user)) || ($delete && !$board->canDelete($this->user)))
			{
				return abort(403);
			}
			
			if ($ban)
			{
				$ban->board_uri = $post->board_uri;
				$ban->save();
			}
			
			$this->log('log.post.ban.local', $post, [
				"board_id"      => $post->board_id,
				"board_uri"     => $post->board_uri,
				"ip"            => $post->author_ip,
				"justification" => $ban->justification,
				"time"          => $banLengthStr,
			]);
			
			if ($delete)
			{
				if ($all)
				{
					$posts = Post::where('author_ip', $post->author_ip)
						->where('board_uri', $board->board_uri);
					
					$this->log('log.post.ban.delete', $post, [
						"board_id"  => $post->board_id,
						"board_uri" => $post->board_uri,
						"posts"     => $posts->count(),
					]);
					
					$posts->delete();
					
					return redirect($board->board_uri);
				}
				else
				{
					$this->log('log.post.ban.delete', $post, [
						"board_id"  => $post->board_id,
						"board_uri" => $post->board_uri,
						"posts"     => 1,
					]);
					
					$post->delete();
					
					if ($post->reply_to)
					{
						return redirect("{$post->board_uri}/thread/{$post->op->board_id}");
					}
					else
					{
						return redirect($board->board_uri);
					}
				}
			}
		}
		
		if ($post->reply_to)
		{
			return redirect("{$post->board_uri}/thread/{$post->op->board_id}#{$post->board_id}");
		}
		else
		{
			return redirect("{$post->board_uri}/thread/{$post->board_id}");
		}
	}
	
	/**
	 * Renders the post edit form.
	 */
	public function getEdit(Request $request, Board $board, $post)
	{
		// Validate the request parameters.
		if(!(($post = $this->validatePost($board, $post)) instanceof Post))
		{
			// If the response isn't a Post, it's a redirect or error.
			// Return the message.
			return $post;
		}
		
		if ($post->canEdit($this->user))
		{
			return View::make(static::VIEW_EDIT, [
				"actions" => ["edit"],
				"form"    => "post",
				"board"   => $board,
				"post"    => $post,
			]);
		}
		
		return abort(403);
	}
	
	/**
	 * Updates a post with the edit.
	 */
	public function patchEdit(Request $request, Board $board, $post)
	{
		// Validate the request parameters.
		if(!(($post = $this->validatePost($board, $post)) instanceof Post))
		{
			// If the response isn't a Post, it's a redirect or error.
			// Return the message.
			return $post;
		}
		
		if ($post->canEdit($this->user))
		{
			$post->subject    = Input::get('subject');
			$post->email      = Input::get('email');
			$post->body       = Input::get('body');
			$post->updated_by = $this->user->user_id;
			$post->save();
			
			$this->log('log.post.edit', $post, [
				"board_id"  => $post->board_id,
				"board_uri" => $post->board_uri,
			]);
			
			return View::make(static::VIEW_EDIT, [
				"actions" => ["edit"],
				"form"    => "post",
				"board"   => $board,
				"post"    => $post,
			]);
		}
		
		return abort(403);
	}
	
	/**
	 * Stickies a thread.
	 */
	public function anySticky(Request $request, Board $board, $post, $sticky = true)
	{
		// Validate the request parameters.
		if(!(($post = $this->validatePost($board, $post)) instanceof Post))
		{
			// If the response isn't a Post, it's a redirect or error.
			// Return the message.
			return $post;
		}
		
		if ($post->canSticky($this->user))
		{
			$post->setSticky( $sticky )->save();
			
			$this->log($sticky ? 'log.post.sticky' : 'log.post.unsticky', $post, [
				"board_id"  => $post->board_id,
				"board_uri" => $post->board_uri,
			]);
			
			return redirect("{$board->board_uri}/thread/{$post->board_id}");
		}
		
		return abort(403);
	}
	
	/**
	 * Unstickies a thread.
	 */
	public function anyUnsticky(Request $request, Board $board, $post)
	{
		// Redirect to anySticky with a flag denoting an unsticky.
		return $this->anySticky($request, $board, $post, false);
	}
	
	
	/**
	 * Check the request for all post controller methods.
	 *
	 * @return HttpException|RedirectResponse|\App\Post
	 */
	protected function validatePost(Board $board, $post)
	{
		if ($post instanceof Post)
		{
			return $post;
		}
		
		// If no post is specified, we can't do anything.
		// Push the user to the index.
		if (is_null($post))
		{
			return redirect($board->board_uri);
		}
		
		// Find the post.
		$post = $board->getLocalReply($post);
		
		if (!$post)
		{
			return abort(404);
		}
		
		return $post;
	}
}
