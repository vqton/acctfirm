from django.shortcuts import render, HttpResponse
from .mailerlite import send_email
import datetime
from django.shortcuts import render, redirect
from .forms import ImageForm, AboutUsForm, ContactForm
from .models import AboutUs, Footer, Service
from django.core.mail import send_mail

# Create your views here.


def send_email(request):
    subject = "Subject of the email"
    message = "Message body of the email"
    from_email = "vuquangton@zohomail.com"
    recipient_list = ["vuquangton@outlook.com"]
    send_mail(subject, message, from_email, recipient_list)
    return HttpResponse("Email sent successfully")


def home(request):
    records = Footer.objects.first()
    current_year = str(datetime.datetime.now().year)
    records.copyright = records.copyright.replace("_year", current_year)
    records.save()
    return render(request, "landingpage\landingpage.html", {"rows_footer": records})


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
    records = Footer.objects.first()
    current_year = str(datetime.datetime.now().year)
    records.copyright = records.copyright.replace("_year", current_year)
    records.save()
    context = {"about_us": about_us, "rows_footer": records}
    return render(request, "aboutus.html", context)


def services(request):
    services = Service.objects.all()
    records = Footer.objects.first()
    current_year = str(datetime.datetime.now().year)
    records.copyright = records.copyright.replace("_year", current_year)
    records.save()

    context = {"services": services, "rows_footer": records}
    return render(request, "services.html", context)


def success_view(request):
    return render(
        request,
        "success.html",
    )


def contact_view(request):
    if request.method == "POST":
        form = ContactForm(request.POST)
        if form.is_valid():
            contact = form.save(commit=False)
            contact.processed_by = request.user
            contact.save()
            send_email(request)
        return redirect("success")
    else:
        form = ContactForm()
    return render(request, "contact.html", {"form": form})
