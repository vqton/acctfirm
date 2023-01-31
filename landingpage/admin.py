from django.contrib import admin
from .models import Image

# Register your models here.
from django.utils.html import format_html


class ImageAdmin(admin.ModelAdmin):
    list_display = ("name", "thumbnail")

    def thumbnail(self, obj):
        print(obj.image.url)
        return format_html('<img src="{}" width="50"/>'.format(obj.image.url))

    thumbnail.short_description = "Image"


admin.site.register(Image, ImageAdmin)
