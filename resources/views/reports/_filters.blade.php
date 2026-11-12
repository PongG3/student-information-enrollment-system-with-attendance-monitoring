{{-- Report filter fields. Shown inline before a report is on screen, and inside the Change filters dialog after. --}}
<form method="GET" action="{{ route('reports.show', $slug) }}" class="row g-3 align-items-end" id="{{ $formId }}">
    @foreach ($fields as $field)
        <div class="{{ $col }}">
            @if ($field['type'] === 'checkbox')
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="{{ $field['name'] }}" value="1" id="{{ $formId }}-{{ $field['name'] }}" @checked(! empty($filters[$field['name']]))>
                    <label class="form-check-label" for="{{ $formId }}-{{ $field['name'] }}">{{ $field['label'] }}</label>
                </div>
            @else
                <label class="form-label small text-secondary mb-1" for="{{ $formId }}-{{ $field['name'] }}">{{ $field['label'] }}</label>
                @if ($field['type'] === 'select')
                    <select id="{{ $formId }}-{{ $field['name'] }}" name="{{ $field['name'] }}" class="form-select">
                        @foreach ($field['options'] as $value => $label)
                            <option value="{{ $value }}" @selected((string) ($filters[$field['name']] ?? '') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @else
                    <input id="{{ $formId }}-{{ $field['name'] }}" type="{{ $field['type'] }}" name="{{ $field['name'] }}"
                           value="{{ $filters[$field['name']] ?? '' }}" class="form-control"
                           @if ($field['type'] === 'number') min="1" max="100" @endif
                           @if ($field['type'] === 'text') maxlength="20" @endif>
                @endif
            @endif
            @error($field['name'])<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    @endforeach
    @unless ($inDialog)
        <div class="col-auto">
            <button class="btn btn-navy">Show report</button>
            <a href="{{ route('reports.show', $slug) }}" class="btn btn-link">Reset</a>
        </div>
    @endunless
</form>
