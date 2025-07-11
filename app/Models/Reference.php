<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reference extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'lecturer_id', 'reference_type', 'status', 'reference_description', 'request_type', 'reference_rejection_reason', 'reference_email', 'payment_processed', 'document_path', 'institution_id'];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'reference_id');
    }

    public function dispute()
    {
        return $this->hasOne(Dispute::class, 'reference_id');
    }

    public function disputes()
    {
        return $this->hasMany(Dispute::class);
    }

}

