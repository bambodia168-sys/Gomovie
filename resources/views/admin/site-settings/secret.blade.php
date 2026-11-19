<label class="gm-studio-field">
  <span>{{ $label }}</span>
  <input class="gm-studio-input" type="password" name="{{ $key }}" autocomplete="new-password"
         placeholder="{{ $credentials[$key] ? 'Configured · enter to replace' : 'Enter credential' }}">
</label>
