@extends('layouts.app')

@section('content')
<div class="container">
    <h2>⚡ AJAX Admin Console</h2>

    <div class="card p-3 mb-3">
        <input id="name" class="form-control mb-2" placeholder="Ad">
        <input id="email" class="form-control mb-2" placeholder="Email">
        <input id="phone" class="form-control mb-2" placeholder="Telefon">
        <button onclick="addTeacher()" class="btn btn-success">Ekle</button>
    </div>

    <input id="search" class="form-control mb-3" placeholder="Ara..." onkeyup="filterTable()">

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th><th>Ad</th><th>Email</th><th>Telefon</th><th>İşlem</th>
            </tr>
        </thead>
        <tbody id="teacherTable"></tbody>
    </table>
</div>

<script>
const csrf = '{{ csrf_token() }}';

// 📥 Liste
async function loadTeachers() {
    const res = await fetch('{{ route('console.list') }}');
    const data = await res.json();

    let html = '';
    data.forEach(t => {
        html += `
        <tr>
            <td>${t.id}</td>
            <td>${t.name}</td>
            <td>${t.email}</td>
            <td>${t.phone}</td>
            <td>
                <button class="btn btn-danger btn-sm"
                    onclick="deleteTeacher(${t.id})">
                    Sil
                </button>
            </td>
        </tr>`;
    });

    document.getElementById('teacherTable').innerHTML = html;
}

// ➕ Ekle
async function addTeacher() {
    const res = await fetch('{{ route('console.store') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify({
            name: name.value,
            email: email.value,
            phone: phone.value
        })
    });

    if (res.ok) {
        loadTeachers();
        name.value = email.value = phone.value = '';
    }
}

// ❌ Sil
async function deleteTeacher(id) {
    if (!confirm('Silinsin mi?')) return;

    await fetch(`/console/delete/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf }
    });

    loadTeachers();
}

// 🔍 Arama
function filterTable() {
    const q = search.value.toLowerCase();
    document.querySelectorAll('#teacherTable tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(q)
            ? ''
            : 'none';
    });
}

loadTeachers();
</script>
@endsection
