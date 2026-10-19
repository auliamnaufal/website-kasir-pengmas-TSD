<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Barryvdh\DomPDF\Facade\Pdf;

class ProductController extends Controller
{
    public function search(Request $request)
    {
    // Get the search query
    $searchTerm = $request->get('searchTerm');

    // Ensure that the search term is treated as a string for product_id comparison
    $products = Product::where('product_name', 'LIKE', '%' . $searchTerm . '%')
        ->orWhereRaw("CAST(product_id AS CHAR) LIKE ?", ['%' . $searchTerm . '%'])
        ->get();

    // Return the search result as JSON
    return response()->json($products);
    }

    // Menampilkan halaman produk
    public function index(Request $request)
    {
        $limit = 100;
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        // Ambil produk berdasarkan offset dan limit
        $products = Product::offset($offset)->limit($limit)->get();

        // Ambil total produk untuk menghitung navigasi halaman
        $totalProducts = Product::count();
        $totalPages = ceil($totalProducts / $limit);

        // Ambil semua kategori untuk form tambah produk
        $categories = Category::all();

        $categories = Category::with('products')->get();

        return view('contents.produk', compact('products', 'categories', 'page', 'totalPages'));
    }

    // Fungsi untuk menyimpan produk baru
    public function store(Request $request)
    {
        // Validasi data input
        $request->validate([
            'product_name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,category_id',
            'quantity_in_stock' => 'required|integer',
            'harga_beli' => 'required|integer|min:0',
            'harga_jual' => 'required|integer|min:0',
            'gambar' => 'nullable|image|max:2048',
            'deskripsi' => 'nullable|string',
            'kode_perkiraan' => 'nullable|string|max:255',
        ]);

        // Cek apakah produk dengan nama yang sama sudah ada (case-insensitive)
        $productExists = Product::whereRaw('LOWER(product_name) = ?', [strtolower($request->product_name)])->exists();
    
        if ($productExists) {
            return redirect()->back()->withErrors(['product_name' => 'Produk dengan nama yang sama sudah ada.'])->withInput();
        }
    
        // Simpan data produk
        $data = $request->all();
        if ($request->hasFile('gambar')) {
            $imageName = time() . '.' . $request->gambar->extension();
            $request->gambar->move(public_path('products'), $imageName);
            $data['gambar'] = 'products/' . $imageName;  // Store the path in the database
        }
    
        Product::create($data);

        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan.');
    }
    

    // Fungsi untuk mengedit produk
    public function update(Request $request, $id)
    {
        // Validasi input
        $request->validate([
            'product_name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,category_id',
            'quantity_in_stock' => 'required|integer',
            'harga_beli' => 'required|numeric',
            'harga_jual' => 'required|numeric',
            'gambar' => 'nullable|image|max:2048',
            'deskripsi' => 'nullable|string',
            'kode_perkiraan' => 'nullable|string|max:255',
        ]);

    
        // Temukan produk berdasarkan ID
        $product = Product::findOrFail($id);

        if (strtolower($product->product_name) != strtolower($request->product_name)) {
            $productExists = Product::whereRaw('LOWER(product_name) = ?', [strtolower($request->product_name)])->exists();
        
            if ($productExists) {
                return redirect()->back()->withErrors(['product_name' => 'Produk dengan nama yang sama sudah ada.'])->withInput();
            }
        }
    
        // Ambil semua data dari request
        $data = $request->all();

        $data['harga_beli'] = (float) str_replace(['.', ','], ['', '.'], $request->harga_beli);
        $data['harga_jual'] = (float) str_replace(['.', ','], ['', '.'], $request->harga_jual);
    
        // Jika ada file gambar yang diupload
        if ($request->hasFile('gambar')) {
            $imageName = time() . '.' . $request->gambar->extension();
            $request->gambar->move(public_path('assets/images'), $imageName);
            $data['gambar'] = 'assets/images/' . $imageName;
        } else {
            // Jika tidak ada gambar yang diupload, tetap gunakan gambar lama
            $data['gambar'] = $product->gambar;
        }
    
        // Update produk
        $product->update($data);
    
        // Redirect ke halaman produk dengan pesan sukses
        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui.');
    }
    

    public function destroy($id)
    {
        // Hapus produk berdasarkan product_id
        $product = Product::where('product_id', $id)->firstOrFail(); // Cek apakah produk ada
    
        // Hapus gambar dari direktori lokal jika ada
        $pathimg = public_path($product->gambar); // Dapatkan path gambar
    
        if (File::exists($pathimg)) {
            File::delete($pathimg); // Hapus gambar dari direktori lokal
        }
    
        $product->delete(); // Hapus produk
    
        // Redirect ke halaman produk dengan pesan sukses
        return redirect()->route('products.index')->with('success', 'Produk berhasil dihapus.');
    }
    
    // ProductController.php

    public function storeCategory(Request $request)
    {
        $request->validate([
            'category_name' => 'required|string|max:255',
        ]);

        Category::create([
            'category_name' => $request->category_name,
        ]);

        return redirect()->route('products.index')->with('success', 'Kategori berhasil ditambahkan.');
    }
    public function destroyCategory($category_id)
    {
        // Temukan kategori berdasarkan ID
        $category = Category::find($category_id);
    
        // Jika kategori tidak ditemukan, kembalikan respons error
        if (!$category) {
            return response()->json(['error' => 'Kategori tidak ditemukan.'], 404);
        }
    
        // Periksa apakah kategori memiliki produk
        if ($category->products()->count() > 0) {
            return response()->json(['error' => 'Tidak dapat menghapus kategori ini karena masih ada produk yang terkait.'], 400);
        }
    
        // Hapus kategori
        $category->delete();
    
        return response()->json(['success' => 'Kategori berhasil dihapus.']);
    }
    
    
    public function updateStock(Request $request, $id)
    {
        $request->validate([
            'jumlah_stok' => 'required|integer|min:1',
            'mode' => 'required|in:tambah,kurang',
        ]);

    
        // Temukan produk berdasarkan ID
        $product = Product::findOrFail($id);
    
        if ($request->mode === 'tambah') {
            $product->quantity_in_stock += $request->jumlah_stok;
            $message = 'Stok berhasil ditambahkan.';
        } else if ($request->mode === 'kurang') {
            $product->quantity_in_stock -= $request->jumlah_stok;
    
            // Pastikan stok tidak negatif
            $product->quantity_in_stock = max(0, $product->quantity_in_stock);
            $message = 'Stok berhasil dikurangi.';
        }
    
        $product->save();
    
        // Redirect dengan pesan sukses ke produk.index
        return redirect()->route('products.index')->with('success', $message);
    }
    public function printBarcode(Request $request)
    {
        // Validasi input
        $request->validate([
            'product_id' => 'required',
            'print_quantity' => 'required|integer|min:1',
        ]);
    
        // Ambil produk berdasarkan product_id
        $product = Product::findOrFail($request->product_id);
    
        // Hitung jumlah barcode berdasarkan jumlah lembar (pastikan bilangan bulat)
        $lembar = (int) floor($request->print_quantity); // Pastikan angka bulat (tidak ada float)
        $quantity = $lembar * 44; // 1 lembar = 44 barcode
    
        // Render view untuk menampilkan barcode
        return view('barcode_preview', compact('product', 'quantity'));
    }
    
    
    // public function printBarcode(Request $request)
    // {
    //     // Validasi input
    //     $request->validate([
    //         'product_id' => 'required',
    //         'print_quantity' => 'required|integer|min:1',
    //     ]);

    //     // Ambil produk berdasarkan product_id
    //     $product = Product::findOrFail($request->product_id);
    //     $quantity = $request->print_quantity;

    //     // Render view dengan variabel 'isPrint' sebagai tanda print preview
    //     return view('products.index', compact('product', 'quantity'))->with('isPrint', true);
    // }
}