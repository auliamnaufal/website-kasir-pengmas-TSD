<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Milon\Barcode\DNS1D;

class Product extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_id';
    public $incrementing = false; // Supaya product_id tidak auto-increment

    protected $fillable = [
        'product_name',
        'category_id',
        'quantity_in_stock',
        'gambar',
        'harga_beli',
        'harga_jual',
        'deskripsi',
        'kode_perkiraan',
    ];

    // Event saat produk sedang dibuat
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            $product->product_id = self::generateProductId();
        });
    }

    private static function generateProductId()
    {
        $today = now(); // Ambil tanggal saat ini
        $datePart = $today->format('ymd'); // Format YYMMDD
        
        // Ambil nomor urut berdasarkan tanggal dan ambil tiga digit terakhir
        $count = self::whereDate('created_at', $today)->count(); 
        $orderPart = str_pad($count + 1, 3, '0', STR_PAD_LEFT); // Nomor urut diisi dengan 0 di depan

        return $datePart . $orderPart; // Gabungkan semua
    }

    

    // A product belongs to a category
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    // A product has one stock record
    public function stock()
    {
        return $this->hasOne(Stock::class, 'product_id', 'product_id');
    }

    // A product can appear in many order details
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'product_id', 'product_id');
    }

    // Fungsi untuk generate barcode dalam base64
    public function getBarcodeBase64()
    {
        $barcode = new DNS1D();
        $barcodeBase64 = $barcode->getBarcodePNG((string)$this->product_id, 'C128', 3, 50, [0, 0, 0], False);
        return $barcodeBase64;
    }
}
