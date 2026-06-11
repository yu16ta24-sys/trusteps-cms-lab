<?php
/**
 * post_deploy.php - v0.21.8
 * - Industry model: add parent/children relations
 * - companies/index.blade.php: industry select -> optgroup hierarchy
 * - companies/candidates.blade.php: industry select -> optgroup hierarchy
 */

$base = '/var/www/trusteps-cms-lab';

// ====== 1. Industry.php にリレーション追加 ======
$modelPath = $base . '/app/Models/Industry.php';
$modelContent = file_get_contents($modelPath);

if (strpos($modelContent, 'public function children()') !== false) {
    echo "[1] Industry.php: already has children(), skip\n";
} else {
    $old = "    protected \$casts = [
        'is_active' => 'boolean',
    ];
}";
    $new = "    protected \$casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return \$this->belongsTo(Industry::class, 'parent_id');
    }

    public function children()
    {
        return \$this->hasMany(Industry::class, 'parent_id')->orderBy('sort_order');
    }
}";
    $result = str_replace($old, $new, $modelContent);
    if ($result === $modelContent) {
        echo "[1] Industry.php: str_replace did not match, SKIP\n";
    } else {
        file_put_contents($modelPath, $result);
        echo "[1] Industry.php: parent/children relations added OK\n";
    }
}

// ====== 共通: 新しい optgroup セレクト ======
$newSelect = '                            <div class="field" style="margin-bottom:0;">
                                <label for="industry_id">業種</label>
                                <select id="industry_id" name="industry_id">
                                    <option value="">すべて</option>
                                    @foreach ($industries->whereNull(\'parent_id\') as $parent)
                                        @php $children = $industries->where(\'parent_id\', $parent->id); @endphp
                                        @if ($children->isNotEmpty())
                                            <optgroup label="{{ $parent->name }}">
                                                @foreach ($children as $child)
                                                    <option value="{{ $child->id }}" @selected((string) request(\'industry_id\') === (string) $child->id)>
                                                        {{ $child->name }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @else
                                            <option value="{{ $parent->id }}" @selected((string) request(\'industry_id\') === (string) $parent->id)>
                                                {{ $parent->name }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>';

$oldSelect = '                            <div class="field" style="margin-bottom:0;">
                                <label for="industry_id">業種</label>
                                <select id="industry_id" name="industry_id">
                                    <option value="">すべて</option>
                                    @foreach ($industries as $industry)
                                        <option value="{{ $industry->id }}" @selected((string) request(\'industry_id\') === (string) $industry->id)>
                                            {{ $industry->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>';

// ====== 2. companies/index.blade.php ======
$indexPath = $base . '/resources/views/companies/index.blade.php';
$indexContent = file_get_contents($indexPath);

if (strpos($indexContent, 'optgroup') !== false) {
    echo "[2] companies/index.blade.php: already has optgroup, skip\n";
} elseif (strpos($indexContent, $oldSelect) === false) {
    echo "[2] companies/index.blade.php: old pattern not found, SKIP\n";
} else {
    $result = str_replace($oldSelect, $newSelect, $indexContent);
    file_put_contents($indexPath, $result);
    echo "[2] companies/index.blade.php: industry select -> optgroup OK\n";
}

// ====== 3. companies/candidates.blade.php ======
$candidatesPath = $base . '/resources/views/companies/candidates.blade.php';
$candidatesContent = file_get_contents($candidatesPath);

if (strpos($candidatesContent, 'optgroup') !== false) {
    echo "[3] companies/candidates.blade.php: already has optgroup, skip\n";
} elseif (strpos($candidatesContent, $oldSelect) === false) {
    echo "[3] companies/candidates.blade.php: old pattern not found, SKIP\n";
} else {
    $result = str_replace($oldSelect, $newSelect, $candidatesContent);
    file_put_contents($candidatesPath, $result);
    echo "[3] companies/candidates.blade.php: industry select -> optgroup OK\n";
}

echo "\n[post_deploy] Done.\n";
