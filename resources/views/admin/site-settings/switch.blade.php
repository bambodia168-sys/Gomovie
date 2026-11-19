<div class="gm-site-setting-row">
  <div>
    <label for="gm-setting-{{ $key }}">{{ $label }}</label>
    <p id="gm-setting-help-{{ $key }}">{{ $description }}</p>
  </div>
  <div class="gm-site-setting-control">
    <input type="hidden" name="{{ $key }}" value="0">
    <input id="gm-setting-{{ $key }}" class="gm-site-setting-switch" type="checkbox" role="switch"
           name="{{ $key }}" value="1" aria-describedby="gm-setting-help-{{ $key }}"
           @checked((bool) old($key, $values[$key]))>
    <span aria-hidden="true"></span>
  </div>
</div>
