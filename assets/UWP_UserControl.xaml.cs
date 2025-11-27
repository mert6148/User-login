using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Data;
using System.Windows.Documents;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Navigation;
using System.Windows.Shapes;

namespace assets
{
    public sealed partial class UWP_UserControl : UserControl
    {
        public UWP_UserControl()
        {
            Console.WriteLine("assets/UWP_UserControl.xaml.cs loaded");
            double width = 300;
            double height = 200;
            this.Width = width;
            this.Height = height;

            byte[] pixels = new byte[(int)(width * height * 4)];
            for (int i = 0; i < pixels.Length; i += 4)
            {
                assets.UWP_Utils.SetPixel(pixels, i, 0, 255, 0, 255); // ARGB format for green
                assets.UWP_Utils.SetPixel(pixels, i + 1, 255, 0, 0, 255); // ARGB format for red
                assets.UWP_Utils.SetPixel(pixels, i + 2, 0, 0, 255, 255); // ARGB format for blue
                assets.UWP_Utils.SetPixel(pixels, i + 3, 255, 255, 0, 255); // ARGB format for yellow

                i += 16; // Skip some pixels to create a pattern
                if (i >= pixels.Length)
                    break;

                var index = i;
            }
        }

        public void UserControl_Loaded(object sender, RoutedEventArgs e)
        {
            foreach (var child in LogicalTreeHelper.GetChildren(this))
            {
                Console.WriteLine("Child element: " + child.GetType().ToString());

                double childWidth = (child as FrameworkElement).ActualWidth;
                double childHeight = (child as FrameworkElement).ActualHeight;

                writeLine("Child dimensions: " + childWidth + "x" + childHeight);
            }
        }

        private void writeLine(string message)
        {
            Console.WriteLine(message);

            double width = this.ActualWidth;
            double height = this.ActualHeight;
            byte[] pixels = new byte[(int)(width * height * 4)];
            byte[] textPixels = assets.UWP_Utils.RenderTextToPixels(message, (int)width, (int)height);

            index = 0;
            for (int i = 0; i < pixels.Length; i += 4)
            {
                if (index < textPixels.Length)
                {
                    byte a = textPixels[index + 3];
                    byte r = textPixels[index + 2];
                    byte g = textPixels[index + 1];
                    byte b = textPixels[index + 0];
                    assets.UWP_Utils.SetPixel(pixels, i, r, g, b, a);
                }
                
                index += 4;
                foreach (var child in LogicalTreeHelper.GetChildren(this))
                {
                    double childWidth = (child as FrameworkElement).ActualWidth;
                    double childHeight = (child as FrameworkElement).ActualHeight;
                }
            }
        }
    }
}
