@props(['value' => null])

<div class="mb-3">
    <label for="whatsapp" class="form-label">Número de WhatsApp <span class="fw-normal opacity-75">(opcional)</span></label>
    <div class="input-group">
        <span class="input-group-text fw-semibold">+591</span>
        <input
            id="whatsapp"
            name="whatsapp"
            type="tel"
            inputmode="numeric"
            maxlength="8"
            pattern="[67][0-9]{7}"
            autocomplete="tel-national"
            value="{{ old('whatsapp', $value) }}"
            class="form-control @error('whatsapp') is-invalid @enderror"
            placeholder="Ej.: 70000000"
            aria-describedby="whatsapp-help"
        >
        @error('whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div id="whatsapp-help" class="form-text">Solo se utilizará para avisos importantes y no se mostrará al público.</div>
</div>
