@if (isset($post))
{!! Form::model($post, [
	'url'    => Request::url(),
	'method' => "PATCH",
	'files'  => true,
	'id'     => "mod-form",
	'class'  => "form-mod smooth-box",
]) !!}
@else
{!! Form::open([
	'url'    => url($board->board_uri . '/thread/' . ($reply_to ?: "")),
	'files'  => true,
	'method' => "PUT",
	'class'  => "form-post",
]) !!}
@endif
	@include('widgets.messages')
	
	<fieldset class="form-fields">
		<legend class="form-legend">{{ trans("board.legend." . implode($actions, "+")) }}</legend>
		
		<div class="field row-subject label-inline">
			{!! Form::text(
				'subject',
				old('subject'),
				[
					'id'        => "subject",
					'class'     => "field-control",
					'maxlength' => 255,
			]) !!}
			{!! Form::label(
				"subject",
				trans('board.field.subject'),
				[
					'class' => "field-label",
			]) !!}
		</div>
		
		<div class="field row-author label-inline">
			{!! Form::text(
				'author',
				isset($post) ? $post->author . ($post->capcode_id ? " ## {$post->capcode->capcode}" : "") : old('author'),
				[
					'id'        => "author",
					'class'     => "field-control",
					'maxlength' => 255,
					isset($post) && $post->capcode_id ? "disabled" : "data-enabled",
			]) !!}
			{!! Form::label(
				"author",
				trans('board.field.author'),
				[
					'class' => "field-label",
			]) !!}
		</div>
		
		<div class="field row-email label-inline">
			{!! Form::text(
				'email',
				old('email'),
				[
					'id'        => "email",
					'class'     => "field-control",
					'maxlength' => 254,
			]) !!}
			{!! Form::label(
				"email",
				trans('board.field.email'),
				[
					'class' => "field-label",
			]) !!}
		</div>
		
		<div class="field row-post">
			{!! Form::textarea(
				'body',
				old('body'),
				[
					'id'        => "body",
					'class'     => "field-control",
			]) !!}
		</div>
		
		@if ($board->canAttach($user) && !isset($post))
		<div class="field row-file">
			<input class="field-control" id="file" name="files[]" type="file" multiple />
		</div>
		@endif
		
		@if (!$board->canPostWithoutCaptcha($user))
		<div class="field row-captcha">
			<label class="field-label" for="captcha">
				{!! Captcha::img() !!}
				<span class="field-validation"></span>
			</label>
			
			{!! Form::text(
				'captcha',
				"",
				[
					'id'          => "captcha",
					'class'       => "field-control",
					'placeholder' => "Security Code",
			]) !!}
		</div>
		@endif
		
		<div class="field row-submit">
			{!! Form::button(
				trans("board.submit." . implode($actions, "+")),
				[
					'type'      => "submit",
					'class'     => "field-submit",
			]) !!}
			
			@if (!$user->isAnonymous() && !isset($post))
			@if ($user->roles->filter(function($role){return $role->capcode;}))
				<select class="field-capcode" name="capcode">
					<option value=""></option>
					
					@foreach ($user->roles->filter(function($role){return $role->capcode;}) as $role)
						<option value="{!! $role->role_id !!}">{{{ $role->capcode }}}</option>
					@endforeach
				</select>
			@endif
			@endif
		</div>
	</fieldset>
	
@if (!isset($form) || $form)
</form>
@endif