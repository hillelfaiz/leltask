<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NoteController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
        ]);

        $request->user()->notes()->create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Note $note)
    {
        if ($note->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
        ]);

        $note->update($validated);

        return redirect()->back();
    }

    public function destroy(Request $request, Note $note)
    {
        if ($note->user_id !== $request->user()->id) {
            abort(403);
        }
        $note->delete();
        return redirect()->back();
    }

    // FUNGSI AI MENGGUNAKAN GROQ (GRATIS & CEPAT)
    public function formatWithAI(Request $request)
    {
        $request->validate([
            'content' => 'required|string'
        ]);

        $apiKey = env('GROQ_API_KEY');

        if (!$apiKey) {
            return response()->json(['error' => 'API Key Groq belum diatur di file .env'], 500);
        }

        try {
            $systemPrompt = <<<'PROMPT'
Kamu adalah asisten akademik profesional yang bertugas merapikan catatan mentah mahasiswa menjadi catatan belajar yang terstruktur, rapi, dan mudah dipahami.

ATURAN FORMAT OUTPUT (WAJIB DIIKUTI):

1. Gunakan penomoran untuk setiap topik/konsep utama. Format: "1. **Nama Topik**" diikuti penjelasannya.
2. Jika di dalam satu topik ada sub-poin, gunakan bullet points dengan tanda "- " (strip spasi).
3. Gunakan **bold** (diapit dua bintang) untuk istilah penting, nama konsep, atau kata kunci.
4. Setiap topik/konsep harus dipisahkan oleh satu baris kosong agar mudah dibaca.
5. Jika catatan asli memiliki summary atau penjelasan yang kurang jelas, singkat, atau ambigu, PERLUAS dan PERJELAS penjelasannya agar lebih informatif dan mudah dipahami. Tambahkan konteks yang relevan.
6. Perbaiki tata bahasa, ejaan, dan struktur kalimat agar profesional.
7. Gunakan Bahasa Indonesia yang baku dan akademis.

ATURAN YANG DILARANG:
- JANGAN menambahkan teks pembuka seperti "Berikut adalah catatan yang sudah dirapikan:" atau sejenisnya.
- JANGAN menambahkan teks penutup seperti "Semoga membantu!" atau sejenisnya.
- JANGAN menggunakan heading dengan tanda pagar (#). Gunakan penomoran dan bold saja.
- JANGAN mengubah makna asli dari catatan.
- JANGAN menghapus informasi yang sudah ada, hanya boleh memperjelas.

CONTOH OUTPUT YANG DIHARAPKAN:

1. **Pengertian Sistem Informasi**
   - Sistem informasi adalah kombinasi dari teknologi informasi dan aktivitas manusia yang menggunakan teknologi tersebut untuk mendukung operasi dan manajemen.
   - Dalam arti luas, sistem informasi mencakup interaksi antara manusia, proses algoritmik, data, dan teknologi.

2. **Komponen Utama Sistem Informasi**
   - **Hardware**: Perangkat keras seperti komputer, server, dan perangkat jaringan.
   - **Software**: Perangkat lunak yang digunakan untuk memproses data menjadi informasi.
   - **Data**: Fakta mentah yang belum diolah menjadi informasi yang berguna.
   - **Prosedur**: Langkah-langkah atau aturan yang harus diikuti dalam penggunaan sistem.
   - **Manusia**: Pengguna dan pengelola sistem informasi.

Langsung berikan hasil akhirnya saja.
PROMPT;

            // Menggunakan endpoint Groq yang kompatibel dengan OpenAI
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.1-8b-instant', // Model LLaMA 3.1 yang super cepat
                    'messages' => [
                        [
                            'role' => 'system', 
                            'content' => $systemPrompt
                        ],
                        [
                            'role' => 'user', 
                            'content' => 'Rapikan catatan berikut ini:\n\n' . $request->input('content')
                        ]
                    ],
                    'temperature' => 0.3, // Lebih deterministik agar format konsisten
                ]);

            if ($response->successful()) {
                return response()->json([
                    'formatted_content' => $response->json('choices.0.message.content')
                ]);
            }

            return response()->json(['error' => 'Gagal menghubungi AI: ' . $response->body()], 500);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }
}