int first(int x, int y) {
    int result = (x / 42) * y;
    return result;
}

void second(int number) {
    int result = first(number, 10);
}