<div class="relative">
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}"
        value="{{ ($type ?? 'text') === 'password' ? '' : old($name, $value ?? '') }}"
        autocomplete="{{ $autocomplete ?? 'off' }}"
        placeholder=" "
        @if($required ?? true) required @endif
        @if(isset($minlength)) minlength="{{ $minlength }}" @endif
        class="peer block w-full rounded-xl border border-white/15 bg-zinc-950/80 px-4 pb-2.5 pt-6 text-sm text-white outline-none transition placeholder:text-transparent focus:border-[#E50914] focus:ring-2 focus:ring-[#E50914]/20 @error($name) border-red-500 @enderror">
    <label for="{{ $name }}" class="pointer-events-none absolute left-4 top-2.5 origin-left text-xs text-zinc-400 transition-all peer-placeholder-shown:top-[1.15rem] peer-placeholder-shown:text-sm peer-focus:top-2.5 peer-focus:text-xs peer-focus:text-[#F5C518]">{{ $label }}</label>
    @error($name)<p class="mt-1 text-xs text-red-300" role="alert">{{ $message }}</p>@enderror
</div>
