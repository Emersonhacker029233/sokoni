@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-xl font-bold">{{ $product ? __('site.product_form_title_edit') : __('site.product_form_title_new') }}</h1>

    <form
        action="{{ $product ? route('web.account.shop.products.update', $product) : route('web.account.shop.products.store') }}"
        method="post"
        enctype="multipart/form-data"
        class="mt-24 space-y-16"
    >
        @csrf
        @if ($product)
            @method('put')
        @endif

        <div>
            <label for="title" class="text-sm font-medium">{{ __('site.product_form_title_field') }}</label>
            <input type="text" id="title" name="title" value="{{ old('title', $product?->title) }}" required class="input-field mt-4">
            @error('title') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
        </div>

        @php
            // A product's category_id may itself point at a subcategory
            // (parent_id set) or a top-level category (parent_id null) —
            // splitting that back into "which parent" + "which child, if
            // any" is what seeds the two selects below correctly on edit.
            $selectedCategory = $product?->category;
            $selectedParentId = $selectedCategory?->parent_id ?? $selectedCategory?->id;
            $selectedChildId = $selectedCategory?->parent_id ? $selectedCategory->id : null;
        @endphp
        <div
            x-data="{
                parentId: {{ Illuminate\Support\Js::from(old('category_id_parent', $selectedParentId)) }},
                childId: {{ Illuminate\Support\Js::from(old('category_id_child', $selectedChildId) ?: '') }},
                subcategories: {{ Illuminate\Support\Js::from($subcategoriesByParent) }},
                carsCategoryId: {{ Illuminate\Support\Js::from($carsCategoryId) }},
                vehicleMakeModels: {{ Illuminate\Support\Js::from($vehicleMakeModels) }},
                make: {{ Illuminate\Support\Js::from(old('make', $product?->attributeValue('make')) ?: '') }},
                model: {{ Illuminate\Support\Js::from(old('model', $product?->attributeValue('model')) ?: '') }},
                year: {{ Illuminate\Support\Js::from(old('year', $product?->attributeValue('year')) ?: '') }},
                get isCars() { return String(this.childId) === String(this.carsCategoryId); },
            }"
            class="space-y-16"
        >
            <div>
                <label for="category_id_parent" class="text-sm font-medium">{{ __('site.product_form_category') }}</label>
                <select id="category_id_parent" x-model.number="parentId" @change="childId = ''" required class="input-field mt-4">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name(app()->getLocale()) }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
            </div>

            <div x-show="(subcategories[parentId] || []).length > 0">
                <label for="category_id_child" class="text-sm font-medium">{{ __('site.product_form_subcategory') }} <span class="text-sokoni-black/40">{{ __('site.product_form_subcategory_optional') }}</span></label>
                <select id="category_id_child" x-model="childId" class="input-field mt-4">
                    <option value="">{{ __('site.product_form_use_parent_category') }}</option>
                    <template x-for="sub in (subcategories[parentId] || [])" :key="sub.id">
                        <option :value="String(sub.id)" x-text="sub.name"></option>
                    </template>
                </select>
            </div>

            <input type="hidden" name="category_id" :value="childId || parentId">

            {{-- C3 (tester feedback): Make/Model are attributes of a Cars
                 listing, not a third category level — two dependent
                 dropdowns, shown and required only when Cars is selected. --}}
            <div x-show="isCars" x-cloak class="space-y-16">
                <div>
                    <label for="make" class="text-sm font-medium">{{ __('site.product_form_make') }}</label>
                    <select id="make" name="make" x-model="make" @change="model = ''" :required="isCars" class="input-field mt-4">
                        <option value="">{{ __('site.product_form_select_make') }}</option>
                        <template x-for="makeName in Object.keys(vehicleMakeModels)" :key="makeName">
                            <option :value="makeName" x-text="makeName"></option>
                        </template>
                    </select>
                    @error('make') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>
                <div x-show="make">
                    <label for="model" class="text-sm font-medium">{{ __('site.product_form_model') }}</label>
                    <select id="model" name="model" x-model="model" :required="isCars" class="input-field mt-4">
                        <option value="">{{ __('site.product_form_select_model') }}</option>
                        <template x-for="modelName in (vehicleMakeModels[make] || [])" :key="modelName">
                            <option :value="modelName" x-text="modelName"></option>
                        </template>
                    </select>
                    @error('model') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>
                {{-- C4 (tester feedback): Year, the third dependent step —
                     revealed once a model is picked, matching the
                     Make -> Model -> Year build-up, even though its own
                     option list is a flat 1990-current range rather than
                     one narrowed by the chosen model (see DECISIONS.md). --}}
                <div x-show="model">
                    <label for="year" class="text-sm font-medium">{{ __('site.product_form_year') }}</label>
                    <select id="year" name="year" x-model="year" :required="isCars" class="input-field mt-4">
                        <option value="">{{ __('site.product_form_select_year') }}</option>
                        @foreach ($vehicleYears as $yearOption)
                            <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                        @endforeach
                    </select>
                    @error('year') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-16">
            <div>
                <label for="price" class="text-sm font-medium">{{ __('site.product_form_price') }}</label>
                <input type="number" id="price" name="price" value="{{ old('price', $product?->price) }}" required min="0" class="input-field mt-4">
            </div>
            <div>
                <label for="stock" class="text-sm font-medium">{{ __('site.product_form_stock') }}</label>
                <input type="number" id="stock" name="stock" value="{{ old('stock', $product?->stock ?? 1) }}" required min="0" class="input-field mt-4">
            </div>
        </div>

        <div>
            <label for="condition" class="text-sm font-medium">{{ __('site.product_form_condition') }}</label>
            <select id="condition" name="condition" required class="input-field mt-4">
                <option value="new" @selected(old('condition', $product?->condition) === 'new')>{{ __('site.product_form_condition_new') }}</option>
                <option value="used" @selected(old('condition', $product?->condition) === 'used')>{{ __('site.product_form_condition_used') }}</option>
            </select>
        </div>

        <div>
            <label for="description" class="text-sm font-medium">{{ __('site.product_form_description') }}</label>
            <textarea id="description" name="description" rows="4" class="input-field mt-4">{{ old('description', $product?->description) }}</textarea>
        </div>

        <button type="submit" class="btn-primary w-full py-12">{{ $product ? __('site.product_form_save_changes') : __('site.product_form_create') }}</button>
    </form>

    <div class="mt-32">
        <label class="text-sm font-medium">{{ __('site.product_form_photos') }}</label>

        @if (! $product)
            <p class="mt-8 rounded-card border border-dashed border-sokoni-outline bg-sokoni-surface-alt p-16 text-sm text-sokoni-black/60">
                {{ __('site.product_form_save_before_media', ['max' => $maxMediaPerProduct]) }}
            </p>
        @else
            <div
                x-data="productMediaManager({{ $product->id }}, {{ $maxMediaPerProduct }}, {{ $product->media->map(fn ($m) => ['id' => $m->id, 'thumb_path' => $m->thumb_path])->values()->toJson() }})"
                class="mt-8"
            >
                <div
                    x-show="remainingSlots > 0"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="dragOver = false; onFiles($event.dataTransfer.files)"
                    @click="$refs.fileInput.click()"
                    :class="dragOver ? 'border-sokoni-yellow bg-sokoni-yellow/5' : 'border-sokoni-outline'"
                    class="cursor-pointer rounded-card border-2 border-dashed p-24 text-center transition"
                >
                    <p class="text-sm font-medium">{{ __('site.product_form_drag_photos') }}</p>
                    <p class="mt-4 text-xs text-sokoni-black/50">{{ __('site.product_form_photo_hint_prefix') }} <span x-text="remainingSlots"></span> {{ __('site.product_form_photo_hint_suffix') }}</p>
                    <input
                        type="file"
                        x-ref="fileInput"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        class="hidden"
                        @change="onFiles($event.target.files); $event.target.value = ''"
                    >
                </div>

                <p x-show="remainingSlots === 0" class="mt-8 text-xs text-sokoni-black/50">
                    {{ __('site.product_form_media_limit_reached', ['max' => $maxMediaPerProduct]) }}
                </p>

                <div class="mt-16 grid grid-cols-3 gap-8 sm:grid-cols-4">
                    <template x-for="(item, index) in items" :key="item.id ?? item.tempId">
                        <div
                            draggable="true"
                            @dragstart="onDragStart(index)"
                            @dragover.prevent
                            @drop.prevent="onDropReorder(index)"
                            class="group relative aspect-square overflow-hidden rounded-chip bg-sokoni-surface-alt"
                        >
                            <img x-show="item.thumb" :src="item.thumb" alt="" class="h-full w-full object-cover">

                            <span x-show="index === 0 && item.thumb" class="absolute left-4 top-4 rounded-chip bg-sokoni-black/70 px-8 py-2 text-[10px] font-semibold text-white">{{ __('site.product_form_cover_badge') }}</span>

                            <div x-show="item.uploading" class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-black/50 text-white">
                                <span class="text-xs font-medium" x-text="item.progress + '%'"></span>
                                <div class="h-4 w-2/3 overflow-hidden rounded-full bg-white/30">
                                    <div class="h-full bg-sokoni-yellow" :style="`width: ${item.progress}%`"></div>
                                </div>
                            </div>

                            <div x-show="item.error" class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-sokoni-danger/90 p-8 text-center text-white">
                                <span class="text-[11px] leading-tight" x-text="item.error"></span>
                                <button type="button" x-show="item.file" @click="retry(item)" class="rounded-chip bg-white/20 px-8 py-2 text-[10px] font-semibold">{{ __('site.product_form_retry') }}</button>
                            </div>

                            <button
                                type="button"
                                @click="removeItem(item)"
                                aria-label="{{ __('site.product_form_remove_photo') }}"
                                class="absolute right-4 top-4 flex h-24 w-24 items-center justify-center rounded-full bg-black/70 text-white opacity-0 transition group-hover:opacity-100"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-12 w-12"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
                <p class="mt-8 text-xs text-sokoni-black/40" x-show="items.length > 1">{{ __('site.product_form_reorder_hint') }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
