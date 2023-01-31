from django.shortcuts import render
from django.shortcuts import render, redirect
from .forms import ImageForm

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
