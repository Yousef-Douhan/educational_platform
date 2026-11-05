@extends('cms.parent')

@section('title', __('student.schedule.title'))

@section('main-content')
    @include('student.schedule._studio', ['hideHead' => false])
@endsection
