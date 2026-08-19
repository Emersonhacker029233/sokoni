@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-xl font-bold">{{ $product ? 'Edit product' : 'New product' }}</h1>

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
            <label for="title" class="text-sm font-medium">Title</label>
            <input type="text" id="title" name="title" value="{{ old('title', $product?->title) }}" required class="input-field mt-4">
            @error('title') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="category_id" class="text-sm font-medium">Category</label>
            <select id="category_id" name="category_id" required class="input-field mt-4">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $product?->category_id) == $category->id)>{{ $category->name_en }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-16">
            <div>
                <label for="price" class="text-sm font-medium">Price (TSh)</label>
                <input type="number" id="price" name="price" value="{{ old('price', $product?->price) }}" required min="0" class="input-field mt-4">
            </div>
            <div>
                <label for="stock" class="text-sm font-medium">Stock</label>
                <input type="number" id="stock" name="stock" value="{{ old('stock', $product?->stock ?? 1) }}" required min="0" class="input-field mt-4">
            </div>
        </div>

        <div>
            <label for="condition" class="text-sm font-medium">Condition</label>
            <select id="condition" name="condition" required class="input-field mt-4">
                <option value="new" @selected(old('condition', $product?->condition) === 'new')>New</option>
                <option value="used" @selected(old('condition', $product?->condition) === 'used')>Used</option>
            </select>
        </div>

        <div>
            <label for="description" class="text-sm font-medium">Description</label>
            <textarea id="description" name="description" rows="4" class="input-field mt-4">{{ old('description', $product?->description) }}</textarea>
        </div>

        <div>
            <label for="images" class="text-sm font-medium">Photos</label>
            <input type="file" id="images" name="images[]" accept="image/*" multiple class="input-field mt-4">
            @if ($product && $product->media->isNotEmpty())
                <div class="mt-8 flex gap-8">
                    @foreach ($product->media as $media)
                        <img src="{{ $media->thumb_path }}" alt="" class="h-48 w-48 rounded-chip object-cover">
                    @endforeach
                </div>
            @endif
        </div>

        <button type="submit" class="btn-primary w-full py-12">{{ $product ? 'Save changes' : 'Create product' }}</button>
    </form>
</div>
@endsection
