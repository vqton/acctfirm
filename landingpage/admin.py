from django.contrib import admin
from .models import Image, AboutUs
from .forms import AboutUsForm


# Register your models here.
from django.utils.html import format_html


class ImageAdmin(admin.ModelAdmin):
    list_display = ("name", "thumbnail")

    def thumbnail(self, obj):
        print(obj.image.url)
        return format_html('<img src="{}" width="50"/>'.format(obj.image.url))

    thumbnail.short_description = "Image"


admin.site.register(Image, ImageAdmin)


class AboutUsAdmin(admin.ModelAdmin):
    form = AboutUsForm
    list_display = ("title", "slug", "user", "created_at", "updated_at")


admin.site.register(AboutUs, AboutUsAdmin)
