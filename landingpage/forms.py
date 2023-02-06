from django import forms
from .models import Image, AboutUs
from ckeditor.widgets import CKEditorWidget


class ImageForm(forms.ModelForm):
    class Meta:
        model = Image
        fields = ["image", "name", "description"]


class AboutUsForm(forms.ModelForm):
    description = forms.CharField(widget=CKEditorWidget())

    class Meta:
        model = AboutUs
        fields = ["title", "description", "user"]
