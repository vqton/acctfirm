from django.contrib import admin
from .models import Image, AboutUs, Footer, Service, Contact
from .forms import AboutUsForm
from django.core.exceptions import ValidationError
import os

# Register your models here.
from django.utils.html import format_html


class ImageAdmin(admin.ModelAdmin):
    list_display = ("name", "thumbnail")

    def save_image(self, request, obj, form, change):
        super.save_image(request, obj, form, change)

        # Check if the image file already exists
        file_path = obj.image.path
        if os.path.exists(file_path):
            # If the file exists, delete the object and raise a validation error
            obj.delete()
            raise ValidationError("This image already exists")

    def thumbnail(self, obj):
        print(obj.image.url)
        return format_html('<img src="{}" width="50"/>'.format(obj.image.url))

    thumbnail.short_description = "Image"


admin.site.register(Image, ImageAdmin)


class AboutUsAdmin(admin.ModelAdmin):
    form = AboutUsForm
    list_display = ("title", "slug", "user", "created_at", "updated_at")


admin.site.register(AboutUs, AboutUsAdmin)


class FooterAdmin(admin.ModelAdmin):
    list_display = (
        "copyright",
        "company_name",
        "company_address",
        "company_email",
        "company_phone",
        "created_at",
        "updated_at",
    )


admin.site.register(Footer, FooterAdmin)


class ServiceAdmin(admin.ModelAdmin):
    list_display = (
        "name",
        "description",
        "slug",
        "created_at",
        "updated_at",
    )


class ContactAdmin(admin.ModelAdmin):
    list_display = ("name", "subject", "email", "message", "received_at")


admin.site.register(Service, ServiceAdmin)
admin.site.register(Contact, ContactAdmin)
