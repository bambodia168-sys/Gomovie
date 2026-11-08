<div class="gm-auth-field">
  <label for="{{ $name }}">{{ $label }}</label>
  <input id="{{ $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}"
    value="{{ ($type ?? 'text') === 'password' ? '' : old($name,$value ?? '') }}"
    autocomplete="{{ $autocomplete ?? 'off' }}"
    @if($required ?? true) required @endif
    @if(isset($minlength)) minlength="{{ $minlength }}" @endif
    @if(($type ?? 'text')==='email') maxlength="255" @endif
    class="@error($name) is-invalid @enderror">
  @error($name)<p role="alert" class="gm-field-error">{{ $message }}</p>@enderror
</div>
