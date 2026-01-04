@extends('layouts.app')

@section('content')
<div class="container">
    <h2>🎛 Admin Console</h2>

    <a href="{{ route('console.create') }}" class="btn btn-primary mb-3">
        + Yeni Öğretmen
    </a>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-bordered">
        <tr>
            <th>#</th>
            <th>Ad</th>
            <th>Email</th>
            <th>Telefon</th>
            <th>İşlem</th>
        </tr>

        @foreach($teachers as $teacher)
        <tr>
            <td>{{ $teacher->id }}</td>
            <td>{{ $teacher->name }}</td>
            <td>{{ $teacher->email }}</td>
            <td>{{ $teacher->phone }}</td>
            <td>
                <a href="{{ route('console.edit', $teacher) }}" class="btn btn-sm btn-warning">Düzenle</a>

                <form action="{{ route('console.destroy', $teacher) }}"
                      method="POST"
                      style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-danger"
                        onclick="return confirm('Silinsin mi?')">
                        Sil
                    </button>
                </form>
            </td>
        </tr>
        @endforeach
    </table>

    {{ $teachers->links() }}
</div>
@endsection
