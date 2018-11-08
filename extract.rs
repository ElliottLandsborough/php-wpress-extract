use std::env; // accept command line arguments among other things
use std::io;
use std::io::prelude::*;
use std::fs::File;

fn main() -> io::Result<()> {
	let args: Vec<String> = env::args().collect();

	let filename = &args[1];

	println!("Opening {}...", filename);

    let mut f = File::open(filename)?;
	let mut buffer = Vec::new();

	// read the whole file
	f.read_to_end(&mut buffer)?;

	//println!("{}", buffer);

	// read file to string
	let mut buffer = String::new();
	f.read_to_string(&mut buffer)?;

	println!("{}", buffer);

    Ok(())
}