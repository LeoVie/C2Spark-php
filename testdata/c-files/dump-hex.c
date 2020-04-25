void dump_hex(unsigned char* binary, unsigned int length) {
	unsigned int i;
	for (i = 0; i < length; i++) {
		printf("%02x ", binary[i]);
	}
	printf("Total: %i\n", i);
}