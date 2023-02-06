from django.shortcuts import render
from django.shortcuts import render, redirect
from .forms import ImageForm, AboutUsForm
from .models import AboutUs

# Create your views here.


def home(request):
    return render(request, "landingpage\landingpage.html")


def upload_image(request):
    if request.method == "POST":
        form = ImageForm(request.POST, request.FILES)
        if form.is_valid():
            form.save()
            return redirect("list_images")
    else:
        form = ImageForm()
    return render(request, "upload.html", {"form": form})


def about_us(request):
    about_us = AboutUs.objects.all()
    return render(request, "aboutus.html", {"about_us": about_us})
