<?php

namespace App\Modules\Pm\Models;


class KnowledgeCategory extends BaseModel
{
    protected $fillable = ['pid', 'sort', 'title', 'desc', 'status'];

    public function parent()
    {
        return $this->belongsTo(KnowledgeCategory::class, 'pid');
    }

    public function children()
    {
        return $this->hasMany(KnowledgeCategory::class, 'pid')->orderBy('sort')->orderBy('id');
    }

    public function knowledges()
    {
        return $this->hasMany(Knowledge::class, 'cate_id');
    }
}