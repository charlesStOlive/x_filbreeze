@php
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" class="relative z-0">
    <div wire:ignore  
         x-data="{
            comparisons: [],
            state: $wire.{{ $applyStateBindingModifiers("entangle('{$statePath}')", isOptimisticallyLive: false) }},
            v1: @js($getVersion1()),
            v2: @js($getVersion2()),

            initialize() {
                this.compareJson(this.v1, this.v2, []);
            },

            compareJson(obj1, obj2, path) {
                const keys = new Set([...Object.keys(obj1 || {}), ...Object.keys(obj2 || {})]);

                keys.forEach((key) => {
                    const fullPath = [...path, key].join('.');

                    const val1 = obj1?.[key] ?? '';
                    const val2 = obj2?.[key] ?? '';

                    if (typeof val1 === 'object' && typeof val2 === 'object') {
                        this.compareJson(val1, val2, [...path, key]);
                    } else if (typeof val1 === 'string' || typeof val2 === 'string') {
                        if (val1 !== val2) {
                            const diff = Diff.createTwoFilesPatch(fullPath, fullPath, val1, val2);
                            const diffHtml = Diff2Html.html(diff, {
                                drawFileList: false,
                                outputFormat: 'side-by-side',
                                renderNothingWhenEmpty: false,
                                colorScheme: 'auto',
                            });

                            this.comparisons.push({ path: fullPath, diff: diffHtml });
                        }
                    }
                });
            }
        }"
         x-init="initialize()"
         x-cloak>
        <template x-for="item in comparisons" :key="item.path">
            <div class="mb-4" x-show="item.diff">
                <h3 class="text-sm font-bold text-gray-600" x-text="item.path"></h3>
                <div id="diffOutput" class="" x-html="item.diff"></div>
            </div>
        </template>
    </div>
</x-dynamic-component>
