@extends('cms.parent')

@section('title', __('student.notes.title'))

@section('main-content')
    @include('student.notes._studio', ['hideHead' => true])
@endsection
