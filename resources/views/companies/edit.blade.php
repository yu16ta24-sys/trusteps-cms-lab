@extends('layouts.app', ['title' => '企業編集 | TRUSTEPS CMS Lab'])

@section('content')
<main class="content ce">
<style>
.ce { display:grid; gap:18px; max-width:800px; }
.ce-topbar { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; }
.ce-kicker { font-size:11px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:6px; }
.ce-title { margin:0; font-size:24px; font-weight:950; letter-spacing:-.03em; color:var(--text); }
.ce-sub { margin:5px 0 0; font-size:13px; color:var(--muted); }
.ce-sec-label { font-size:10px; font-weight:900; color:var(--muted); letter-spacing:.1em; text-transform:uppercase; margin-bottom:14px; }
</style>

<div class="ce-topbar">
    <div>
        <div class="ce-kicker">企業 #{{ $company->id }} · 編集</div>
        <h1 class="ce-title">{{ $company->display_name }}</h1>
        <p class="ce-sub">基本情報を編集する。スコア・kill_flagsは詳細画面から変更する。</p>
    </div>
    <div class="actions">
        <a class="button light small" href="{{ route('companies.show', $company) }}">キャンセル</a>
    </div>
</div>

@if ($errors->any())
    <div class="error">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('companies.update', $company) }}">
    @csrf
    @method('PUT')

    <section class="card">
        <div class="ce-sec-label">基本情報</div>
        <div class="form-shell">
            <div class="field">
                <label for="display_name">display_name <span style="color:#dc2626;font-size:11px;">必須</span></label>
                <input id="display_name" type="text" name="display_name" value="{{ old('display_name', $company->display_name) }}" required>
            </div>
            <div class="field">
                <label for="legal_name">legal_name</label>
                <input id="legal_name" type="text" name="legal_name" value="{{ old('legal_name', $company->legal_name) }}" placeholder="法人格あり正式名称">
            </div>
            <div class="field">
                <label for="corporate_number">corporate_number</label>
                <input id="corporate_number" type="text" name="corporate_number" value="{{ old('corporate_number', $company->corporate_number) }}" placeholder="13桁法人番号">
            </div>
            <div class="field">
                <label for="status">status</label>
                <select id="status" name="status">
                    <option value="candidate" @selected(old('status', $company->status) === 'candidate')>candidate</option>
                    <option value="confirmed" @selected(old('status', $company->status) === 'confirmed')>confirmed</option>
                </select>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="ce-sec-label">業種・地域</div>
        <div class="form-shell">
            <div class="field">
                <label for="industry_id">業種</label>
                <select id="industry_id" name="industry_id">
                    <option value="">未設定</option>
                    @foreach ($industries->whereNull('parent_id') as $parent)
                        @php $childList = $industries->where('parent_id', $parent->id); @endphp
                        @if ($childList->isNotEmpty())
                            <optgroup label="{{ $parent->name }}">
                                @foreach ($childList as $child)
                                    <option value="{{ $child->id }}" @selected((string) old('industry_id', $company->industry_id) === (string) $child->id)>
                                        {{ $child->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @else
                            <option value="{{ $parent->id }}" @selected((string) old('industry_id', $company->industry_id) === (string) $parent->id)>
                                {{ $parent->name }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="municipality_id">市区町村マスタ</label>
                <select id="municipality_id" name="municipality_id">
                    <option value="">未設定</option>
                    @foreach ($municipalities as $municipality)
                        <option value="{{ $municipality->id }}"
                            data-pref="{{ $municipality->prefecture?->name }}"
                            @selected((string) old('municipality_id', $company->municipality_id) === (string) $municipality->id)>
                            {{ $municipality->prefecture?->name }} / {{ $municipality->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="pref">都道府県（手入力・マスタ外）</label>
                <input id="pref" type="text" name="pref" value="{{ old('pref', $company->pref) }}" placeholder="マスタに存在しない場合のみ">
            </div>
            <div class="field">
                <label for="city">市区町村（手入力・マスタ外）</label>
                <input id="city" type="text" name="city" value="{{ old('city', $company->city) }}" placeholder="マスタに存在しない場合のみ">
            </div>
        </div>
    </section>

    <section class="card">
        <div class="ce-sec-label">Web・ドメイン</div>
        <div class="form-shell">
            <div class="field">
                <label for="primary_url">primary URL</label>
                <input id="primary_url" type="text" name="primary_url" value="{{ old('primary_url', $company->primaryDomain?->url) }}" placeholder="https://example.com">
                @if ($company->primaryDomain)
                    <p class="field-hint">現在：{{ $company->primaryDomain->url }}（変更すると新しいdomainレコードを作成し primary に設定する）</p>
                @endif
            </div>
        </div>
    </section>

    <section class="card">
        <div class="actions">
            <a class="button light small" href="{{ route('companies.show', $company) }}">キャンセル</a>
            <button class="button" type="submit">変更を保存</button>
        </div>
    </section>
</form>

</main>
@endsection
